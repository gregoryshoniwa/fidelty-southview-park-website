<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Services\MessagingService;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:80'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:120', Rule::unique('users')->ignore($request->user()->id)],
            'locale' => ['sometimes', Rule::in(['en', 'sn', 'nd'])],
            'notification_prefs' => ['sometimes', 'array'],
            'notification_prefs.sms' => ['boolean'],
            'notification_prefs.push' => ['boolean'],
            'marketing' => ['sometimes', 'boolean'],
        ]);
        $user = $request->user();
        if (array_key_exists('name', $data)) {
            $data['name'] = strip_tags($data['name']);
        }
        if (array_key_exists('marketing', $data) && $user->resident) {
            $user->resident->update(['consent_marketing_at' => $data['marketing'] ? now() : null]);
            unset($data['marketing']);
        }
        $user->update($data);
        AuditLog::record('user.updated', $user, ['fields' => array_keys($data)]);

        return response()->json(['user' => Present::user($user->fresh())]);
    }

    /** Data subject access: everything we hold about the resident, as JSON. */
    public function export(Request $request)
    {
        $u = $request->user()->load('resident.stand', 'resident.requests.events', 'resident.payments', 'resident.threads.messages', 'resident.documents');
        AuditLog::record('user.data_exported', $u);
        $data = [
            'generated_at' => now()->toIso8601String(),
            'profile' => Present::user($u),
            'consents' => Consent::where('user_id', $u->id)->get(['purpose', 'text_version', 'given_at', 'withdrawn_at']),
            'requests' => $u->resident?->requests->map(fn ($r) => ['reference' => $r->reference, 'status' => $r->status, 'step' => $r->step, 'data' => $r->data, 'events' => $r->events]),
            'payments' => $u->resident?->payments->map(fn ($p) => Present::payment($p)),
            'documents' => $u->resident?->documents->map(fn ($d) => Present::document($d)),
            'messages' => $u->resident?->threads->map(fn ($t) => ['reference' => $t->reference, 'subject' => $t->subject, 'messages' => $t->messages->map(fn ($m) => ['from' => $m->sender_type, 'body' => $m->body, 'at' => $m->created_at])]),
        ];

        return response()->json($data, 200, ['Content-Disposition' => 'attachment; filename="my-southview-data.json"']);
    }

    public function requestDeletion(Request $request, MessagingService $messaging)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $resident = $request->user()->resident;
        $thread = $messaging->openThread($resident, 'Request to delete my account and data', 'Please delete my account and personal data. '.strip_tags((string) ($data['reason'] ?? '')), null, null, 'general');
        AuditLog::record('user.deletion_requested', $request->user());

        return response()->json(['reference' => $thread->reference], 201);
    }
}
