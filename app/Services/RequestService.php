<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Resident;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class RequestService
{
    public const PREFIX = [
        'title-deed-tracker' => 'DEED',
        'home-and-deed-loans' => 'LOAN',
        'my-agreement' => 'AGR',
        'security' => 'SEC',
        'schools' => 'SCH',
    ];

    public function __construct(private NotificationService $notify, private MessagingService $messaging) {}

    public function open(Resident $resident, Service $service, array $data = []): ServiceRequest
    {
        $req = ServiceRequest::create([
            'reference' => Reference::next(self::PREFIX[$service->slug] ?? 'REQ', 'service_requests'),
            'resident_id' => $resident->id,
            'service_id' => $service->id,
            'partner_id' => $service->partner_id,
            'status' => 'waiting_partner',
            'step' => 1,
            'data' => $data ?: null,
        ]);
        $req->events()->create(['actor_type' => 'resident', 'actor_id' => $resident->user_id, 'type' => 'opened', 'payload' => ['service' => $service->name]]);
        AuditLog::record('request.opened', $req);

        if ($service->partner) {
            foreach ($service->partner->users as $staff) {
                $this->notify->notify($staff, 'New request '.$req->reference, $service->name, '/partner/requests/'.$req->reference, 'request', false);
            }
        }

        return $req;
    }

    public function updateStatus(ServiceRequest $req, User $by, ?int $step, ?string $status, ?string $note): ServiceRequest
    {
        $before = ['step' => $req->step, 'status' => $req->status];
        $req->fill(array_filter(['step' => $step, 'status' => $status], fn ($v) => $v !== null));
        if (in_array($req->status, ['closed', 'cancelled'], true)) {
            $req->closed_at = now();
        }
        $req->save();
        $req->events()->create(['actor_type' => 'partner_user', 'actor_id' => $by->id, 'type' => 'status_change', 'payload' => ['from' => $before, 'to' => ['step' => $req->step, 'status' => $req->status], 'note' => $note]]);
        AuditLog::record('request.status_changed', $req, ['to' => ['step' => $req->step, 'status' => $req->status]], $by);

        $steps = $req->stepsList();
        $stepLabel = $steps[$req->step - 1] ?? null;
        $this->notify->notify(
            $req->resident->user,
            $req->service->name.' updated',
            trim(($stepLabel ? "Step {$req->step}: {$stepLabel}. " : '').($note ?? '')),
            '/app/requests/'.$req->reference,
            'request'
        );

        return $req;
    }

    public function storeDocument(Resident $resident, UploadedFile $file, string $kind, ?ServiceRequest $req, User $by): Document
    {
        $path = $file->store('documents/'.$resident->id, 'local');
        $doc = Document::create([
            'resident_id' => $resident->id,
            'service_request_id' => $req?->id,
            'kind' => $kind,
            'original_name' => substr($file->getClientOriginalName(), 0, 200),
            'storage_path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $by->id,
        ]);
        if ($req) {
            $req->events()->create(['actor_type' => $by->id === $resident->user_id ? 'resident' : 'partner_user', 'actor_id' => $by->id, 'type' => 'document_added', 'payload' => ['kind' => $kind]]);
        }
        AuditLog::record('document.uploaded', $doc, ['kind' => $kind], $by);

        return $doc;
    }

    public function streamDocument(Document $doc, User $viewer)
    {
        AuditLog::record('document.viewed', $doc, ['kind' => $doc->kind], $viewer);

        return Storage::disk('local')->download($doc->storage_path, $doc->original_name, [
            'Content-Type' => $doc->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
