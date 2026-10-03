<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Services\MessagingService;
use App\Services\RequestService;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RequestController extends Controller
{
    public function __construct(private RequestService $requests) {}

    public function index(Request $request)
    {
        $items = $request->user()->resident->requests()->with('service', 'partner')->latest()->get();

        return response()->json(['data' => $items->map(fn ($q) => Present::request($q))]);
    }

    public function store(Request $request, Service $service)
    {
        abort_unless($service->enabled && $service->partner_id, 422, 'This service is not open for requests yet.');
        $rules = ['notes' => ['nullable', 'string', 'max:1000']];
        foreach ($service->form_schema ?? [] as $field) {
            $rules['fields.'.$field['name']] = array_merge($field['required'] ?? false ? ['required'] : ['nullable'], ['string', 'max:500']);
        }
        $data = $request->validate($rules);
        $resident = $request->user()->resident;

        $existing = $resident->requests()->where('service_id', $service->id)->whereNotIn('status', ['closed', 'cancelled'])->first();
        if ($existing && in_array($service->slug, ['title-deed-tracker'], true)) {
            return response()->json(['reference' => $existing->reference, 'existing' => true]);
        }

        $clean = collect($data['fields'] ?? [])->map(fn ($v) => strip_tags((string) $v))->all();
        if (! empty($data['notes'])) {
            $clean['notes'] = strip_tags($data['notes']);
        }
        $req = $this->requests->open($resident, $service, $clean);

        return response()->json(['reference' => $req->reference], 201);
    }

    public function show(Request $request, ServiceRequest $serviceRequest)
    {
        $this->own($request, $serviceRequest);
        $serviceRequest->load('service', 'partner', 'events', 'documents', 'thread');

        return response()->json(['data' => Present::request($serviceRequest, true)]);
    }

    public function upload(Request $request, ServiceRequest $serviceRequest)
    {
        $this->own($request, $serviceRequest);
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(Document::KINDS))],
            'file' => ['required', 'file', 'max:'.config('fspra.documents.max_kb'), 'mimes:'.implode(',', config('fspra.documents.mimes'))],
            'consent' => ['accepted'],
        ]);
        $resident = $request->user()->resident;
        if (! $resident->consent_docs_at) {
            $resident->update(['consent_docs_at' => now()]);
        }
        $doc = $this->requests->storeDocument($resident, $data['file'], $data['kind'], $serviceRequest, $request->user());

        return response()->json(['data' => Present::document($doc)], 201);
    }

    public function document(Request $request, Document $document)
    {
        abort_unless($document->resident_id === $request->user()->resident?->id, 404);

        return $this->requests->streamDocument($document, $request->user());
    }

    public function message(Request $request, ServiceRequest $serviceRequest, MessagingService $messaging)
    {
        $this->own($request, $serviceRequest);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $serviceRequest->loadMissing('partner', 'service');
        $thread = $serviceRequest->thread;
        if ($thread) {
            $messaging->post($thread, 'resident', $request->user()->id, strip_tags($data['body']));
        } else {
            $thread = $messaging->openThread($serviceRequest->resident, $serviceRequest->service->name.' '.$serviceRequest->reference, strip_tags($data['body']), $serviceRequest->partner, $serviceRequest->id, 'general');
        }

        return response()->json(['thread' => $thread->reference], 201);
    }

    private function own(Request $request, ServiceRequest $q): void
    {
        abort_unless($q->resident_id === $request->user()->resident?->id, 404);
    }
}
