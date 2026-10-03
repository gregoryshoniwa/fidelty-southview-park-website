<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Broadcast;
use App\Models\Document;
use App\Models\Incident;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Resident;
use App\Models\SchoolInvoice;
use App\Models\SecuritySubscription;
use App\Models\ServiceRequest;
use App\Models\Thread;
use App\Services\MessagingService;
use App\Services\NotificationService;
use App\Services\Reference;
use App\Services\RequestService;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalController extends Controller
{
    private function partner(Request $r): Partner
    {
        return $r->attributes->get('partner');
    }

    private function ownRequest(Request $r, ServiceRequest $q): void
    {
        abort_unless($q->partner_id === $this->partner($r)->id, 404);
    }

    public function me(Request $request)
    {
        $p = $this->partner($request);

        return response()->json([
            'user' => ['name' => $request->user()->name, 'role' => $request->user()->partners()->where('partner_id', $p->id)->first()?->pivot->role],
            'partner' => ['id' => $p->id, 'name' => $p->name, 'type' => $p->type, 'logo' => $p->logoUrl(), 'modules' => $p->modules ?? [], 'steps' => $p->workflow_steps ?? []],
            'partners' => $request->user()->partners()->where('active', true)->get()->map(fn ($x) => ['id' => $x->id, 'name' => $x->name]),
        ]);
    }

    public function stats(Request $request)
    {
        $p = $this->partner($request);
        $base = ServiceRequest::where('partner_id', $p->id);

        return response()->json([
            'open' => (clone $base)->whereNotIn('status', ['closed', 'cancelled'])->count(),
            'waiting_resident' => (clone $base)->where('status', 'waiting_resident')->count(),
            'waiting_payment' => (clone $base)->where('status', 'waiting_payment')->count(),
            'waiting_partner' => (clone $base)->where('status', 'waiting_partner')->count(),
            'closed_30d' => (clone $base)->where('status', 'closed')->where('closed_at', '>=', now()->subDays(30))->count(),
            'unread_messages' => Thread::where('partner_id', $p->id)->whereHas('messages', fn ($m) => $m->whereNull('read_by_staff_at'))->count(),
        ]);
    }

    public function queue(Request $request)
    {
        $p = $this->partner($request);
        $q = ServiceRequest::where('partner_id', $p->id)->with('service', 'partner', 'resident.user', 'resident.stand')->withCount('documents');
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        } else {
            $q->whereNotIn('status', ['closed', 'cancelled']);
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('reference', 'like', "%{$term}%")
                ->orWhereHas('resident.stand', fn ($s) => $s->where('stand_number', 'like', "%{$term}%"))
                ->orWhereHas('resident.user', fn ($u) => $u->where('name', 'like', "%{$term}%")));
        }
        $page = $q->latest('updated_at')->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn ($r) => Present::request($r) + [
                'resident' => ['name' => $r->resident->user->name, 'stand' => $r->resident->stand?->stand_number],
                'documents_count' => $r->documents_count,
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Request $request, ServiceRequest $serviceRequest)
    {
        $this->ownRequest($request, $serviceRequest);
        $serviceRequest->load('service', 'partner', 'events', 'documents', 'thread', 'resident.user', 'resident.stand', 'payments');
        AuditLog::record('partner.request_viewed', $serviceRequest);

        return response()->json(['data' => Present::request($serviceRequest, true) + [
            'resident' => [
                'name' => $serviceRequest->resident->user->name,
                'stand' => $serviceRequest->resident->stand?->stand_number,
                'verified' => $serviceRequest->resident->isVerified(),
                'id_last4' => $serviceRequest->resident->national_id_last4,
            ],
            'payments' => $serviceRequest->payments->map(fn ($x) => Present::payment($x)),
        ]]);
    }

    public function update(Request $request, ServiceRequest $serviceRequest, RequestService $requests)
    {
        $this->ownRequest($request, $serviceRequest);
        $max = max(1, count($serviceRequest->stepsList()));
        $data = $request->validate([
            'step' => ['nullable', 'integer', 'min:1', 'max:'.$max],
            'status' => ['nullable', Rule::in(array_keys(ServiceRequest::STATUSES))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $requests->updateStatus($serviceRequest, $request->user(), $data['step'] ?? null, $data['status'] ?? null, isset($data['note']) ? strip_tags($data['note']) : null);

        return $this->show($request, $serviceRequest->fresh());
    }

    public function document(Request $request, Document $document, RequestService $requests)
    {
        $req = $document->request;
        abort_unless($req && $req->partner_id === $this->partner($request)->id, 404);

        return $requests->streamDocument($document, $request->user());
    }

    public function threads(Request $request)
    {
        $p = $this->partner($request);
        $threads = Thread::where('partner_id', $p->id)->with('partner', 'request', 'resident.user', 'resident.stand')->orderByDesc('last_message_at')->take(100)->get();

        return response()->json(['data' => $threads->map(fn ($t) => Present::thread($t, false, 'staff'))]);
    }

    public function thread(Request $request, Thread $thread)
    {
        abort_unless($thread->partner_id === $this->partner($request)->id, 404);
        $thread->messages()->whereNull('read_by_staff_at')->update(['read_by_staff_at' => now()]);
        $thread->load('messages', 'partner', 'request', 'resident.user', 'resident.stand');

        return response()->json(['data' => Present::thread($thread, true, 'staff')]);
    }

    public function reply(Request $request, Thread $thread, MessagingService $messaging)
    {
        abort_unless($thread->partner_id === $this->partner($request)->id, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $messaging->post($thread, 'partner_user', $request->user()->id, strip_tags($data['body']));
        AuditLog::record('partner.message_sent', $thread);

        return $this->thread($request, $thread);
    }

    public function startThread(Request $request, ServiceRequest $serviceRequest, MessagingService $messaging)
    {
        $this->ownRequest($request, $serviceRequest);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $thread = $serviceRequest->thread ?? Thread::create([
            'reference' => Reference::next('MSG', 'threads'),
            'resident_id' => $serviceRequest->resident_id, 'partner_id' => $serviceRequest->partner_id, 'service_request_id' => $serviceRequest->id,
            'subject' => $serviceRequest->service->name.' '.$serviceRequest->reference, 'category' => 'general', 'status' => 'answered', 'last_message_at' => now(),
        ]);
        $messaging->post($thread, 'partner_user', $request->user()->id, strip_tags($data['body']));

        return response()->json(['thread' => $thread->reference], 201);
    }

    public function broadcasts(Request $request)
    {
        return response()->json(['data' => Broadcast::where('partner_id', $this->partner($request)->id)->latest()->take(50)->get()
            ->map(fn ($b) => ['id' => $b->id, 'body' => $b->body, 'audience' => $b->audience, 'recipients' => $b->recipient_count, 'sent_at' => $b->sent_at?->toIso8601String()])]);
    }

    public function broadcast(Request $request, NotificationService $notify)
    {
        $p = $this->partner($request);
        $data = $request->validate(['body' => ['required', 'string', 'min:5', 'max:480'], 'audience' => ['required', Rule::in(['open_requests', 'all_clients'])]]);
        $q = ServiceRequest::where('partner_id', $p->id);
        if ($data['audience'] === 'open_requests') {
            $q->whereNotIn('status', ['closed', 'cancelled']);
        }
        $residentIds = $q->distinct()->pluck('resident_id');
        $residents = Resident::whereIn('id', $residentIds)->with('user')->get();
        foreach ($residents as $r) {
            $notify->notify($r->user, 'Update from '.$p->name, strip_tags($data['body']), '/app/notifications', 'broadcast');
        }
        $b = Broadcast::create(['partner_id' => $p->id, 'sent_by' => $request->user()->id, 'body' => strip_tags($data['body']), 'audience' => $data['audience'], 'recipient_count' => $residents->count(), 'sent_at' => now()]);
        AuditLog::record('partner.broadcast_sent', $b, ['recipients' => $residents->count()]);

        return response()->json(['recipients' => $residents->count()], 201);
    }

    public function settlements(Request $request)
    {
        $p = $this->partner($request);
        $payments = Payment::where('partner_id', $p->id)->where('status', 'paid')->latest('paid_at')->take(200)->get();

        return response()->json([
            'total' => (string) $payments->where('currency', 'USD')->sum('amount'),
            'totals' => $payments->groupBy('currency')->map(fn ($g) => number_format($g->sum('amount'), 2, '.', '')),
            'count' => $payments->count(),
            'data' => $payments->map(fn ($x) => Present::payment($x)),
        ]);
    }

    public function invoices(Request $request)
    {
        $p = $this->partner($request);

        return response()->json(['data' => SchoolInvoice::where('partner_id', $p->id)->with('resident.user')->latest()->take(200)->get()->map(fn ($i) => [
            'id' => $i->id, 'learner_ref' => $i->learner_ref, 'learner_name' => $i->learner_name, 'parent' => $i->resident?->user->name,
            'description' => $i->description, 'amount' => (string) $i->amount, 'currency' => $i->currency, 'due_on' => $i->due_on->toDateString(), 'status' => $i->status,
        ])]);
    }

    public function storeInvoice(Request $request, NotificationService $notify)
    {
        $p = $this->partner($request);
        $data = $request->validate([
            'learner_ref' => ['required', 'string', 'max:40'], 'learner_name' => ['required', 'string', 'max:120'],
            'parent_stand' => ['nullable', 'string', 'max:20'], 'description' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'min:1', 'max:20000'], 'currency' => ['required', Rule::in(['USD', 'ZWG'])], 'due_on' => ['required', 'date', 'after_or_equal:today'],
        ]);
        $resident = null;
        if (! empty($data['parent_stand'])) {
            $resident = Resident::whereHas('stand', fn ($s) => $s->where('stand_number', strtoupper($data['parent_stand'])))->where('verification_status', 'verified')->first();
        }
        $inv = SchoolInvoice::create(['partner_id' => $p->id, 'learner_ref' => strip_tags($data['learner_ref']), 'learner_name' => strip_tags($data['learner_name']), 'resident_id' => $resident?->id,
            'description' => strip_tags($data['description']), 'amount' => $data['amount'], 'currency' => $data['currency'], 'due_on' => $data['due_on']]);
        if ($resident) {
            $notify->notify($resident->user, 'New invoice from '.$p->name, $inv->learner_name.': '.$inv->currency.' '.$inv->amount.' due '.$inv->due_on->toFormattedDateString(), '/app/schools', 'invoice');
        }
        AuditLog::record('partner.invoice_created', $inv);

        return response()->json(['id' => $inv->id, 'linked_to_parent' => (bool) $resident], 201);
    }

    public function incidents(Request $request)
    {
        $p = $this->partner($request);
        $subs = SecuritySubscription::where('partner_id', $p->id)->pluck('resident_id');

        return response()->json(['data' => Incident::where(fn ($w) => $w->where('partner_id', $p->id)->orWhereIn('resident_id', $subs))
            ->with('resident.user', 'resident.stand')->latest()->take(100)->get()->map(fn ($i) => [
                'reference' => $i->reference, 'category' => $i->category, 'location' => $i->location, 'description' => $i->description, 'status' => $i->status,
                'resident' => $i->resident->user->name, 'stand' => $i->resident->stand?->stand_number, 'at' => $i->created_at->toIso8601String(),
            ])]);
    }

    public function updateIncident(Request $request, Incident $incident, NotificationService $notify)
    {
        $p = $this->partner($request);
        $subs = SecuritySubscription::where('partner_id', $p->id)->pluck('resident_id')->all();
        abort_unless($incident->partner_id === $p->id || in_array($incident->resident_id, $subs, true), 404);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'responding', 'resolved'])]]);
        $incident->update(['status' => $data['status'], 'partner_id' => $p->id]);
        $notify->notify($incident->resident->user, 'Incident '.$incident->reference.' is '.$data['status'], $p->name.' updated your report.', '/app/security', 'incident');

        return response()->json(['ok' => true]);
    }

    public function export(Request $request): StreamedResponse
    {
        $p = $this->partner($request);
        $rows = ServiceRequest::where('partner_id', $p->id)->with('service', 'resident.user', 'resident.stand')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->orderBy('id')->get();
        AuditLog::record('partner.export', null, ['partner' => $p->id, 'rows' => $rows->count()]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['reference', 'service', 'resident', 'stand', 'status', 'step', 'opened', 'updated']);
            foreach ($rows as $r) {
                fputcsv($out, array_map(fn ($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'".$v : $v, [
                    $r->reference, $r->service->name, $r->resident->user->name, $r->resident->stand?->stand_number, $r->status, $r->step,
                    $r->created_at->toDateString(), $r->updated_at->toDateString(),
                ]));
            }
            fclose($out);
        }, $p->slug.'-requests-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }
}
