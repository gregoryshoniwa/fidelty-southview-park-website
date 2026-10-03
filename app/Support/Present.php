<?php

namespace App\Support;

use App\Models\Document;
use App\Models\Message;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\Thread;
use App\Models\User;
use App\Services\Phone;

/** Small, explicit serialisers so the API never leaks internal columns. */
final class Present
{
    public static function user(User $u): array
    {
        $r = $u->resident;

        return [
            'id' => $u->id,
            'name' => $u->name,
            'phone_masked' => $u->phone ? Phone::mask($u->phone) : null,
            'has_phone' => (bool) $u->phone,
            'auth_provider' => $u->auth_provider,
            'email' => $u->email,
            'locale' => $u->locale,
            'notification_prefs' => $u->notification_prefs ?? ['sms' => true, 'push' => true],
            'roles' => $u->getRoleNames(),
            'is_partner' => $u->partners()->exists(),
            'is_committee' => $u->isCommittee(),
            'resident' => $r ? [
                'verification_status' => $r->verification_status,
                'verified_at' => $r->verified_at?->toIso8601String(),
                'stand' => $r->stand?->stand_number,
                'phone_on_file_masked' => $r->phone_on_file_masked,
                'id_last4' => $r->national_id_last4,
            ] : null,
        ];
    }

    public static function request(ServiceRequest $q, bool $detail = false): array
    {
        $steps = $q->stepsList();
        $out = [
            'reference' => $q->reference,
            'service' => ['name' => $q->service->name, 'slug' => $q->service->slug],
            'partner' => $q->partner ? ['name' => $q->partner->name, 'logo' => $q->partner->logoUrl()] : null,
            'status' => $q->status,
            'status_label' => ServiceRequest::STATUSES[$q->status] ?? $q->status,
            'step' => $q->step,
            'steps' => $steps,
            'step_label' => $steps[$q->step - 1] ?? null,
            'data' => $q->data,
            'created_at' => $q->created_at->toIso8601String(),
            'updated_at' => $q->updated_at->toIso8601String(),
        ];
        if ($detail) {
            $out['events'] = $q->events->map(fn ($e) => [
                'type' => $e->type, 'actor' => $e->actor_type, 'payload' => $e->payload, 'at' => $e->created_at->toIso8601String(),
            ]);
            $out['documents'] = $q->documents->map(fn ($d) => self::document($d));
            $out['thread'] = $q->thread?->reference;
        }

        return $out;
    }

    public static function document(Document $d): array
    {
        return [
            'id' => $d->ulid, 'kind' => $d->kind, 'kind_label' => Document::KINDS[$d->kind] ?? $d->kind,
            'name' => $d->original_name, 'size' => $d->size, 'mime' => $d->mime, 'uploaded_at' => $d->created_at->toIso8601String(),
        ];
    }

    public static function payment(Payment $p): array
    {
        return [
            'id' => $p->ulid,
            'biller' => $p->biller_code,
            'biller_label' => Payment::BILLERS[$p->biller_code]['label'] ?? $p->biller_code,
            'reference' => $p->biller_reference,
            'amount' => (string) $p->amount,
            'platform_fee' => (string) $p->platform_fee,
            'total' => (string) $p->total,
            'currency' => $p->currency,
            'status' => $p->status,
            'token' => $p->status === 'paid' ? $p->token_or_voucher : null,
            'paid_at' => $p->paid_at?->toIso8601String(),
            'created_at' => $p->created_at->toIso8601String(),
            'has_receipt' => (bool) $p->receipt_path,
        ];
    }

    public static function thread(Thread $t, bool $detail = false, string $viewer = 'resident'): array
    {
        $out = [
            'reference' => $t->reference,
            'subject' => $t->subject,
            'category' => $t->category,
            'status' => $t->status,
            'with' => $t->partner?->name ?? 'Committee',
            'partner_logo' => $t->partner?->logoUrl(),
            'request' => $t->request?->reference,
            'last_message_at' => $t->last_message_at?->toIso8601String(),
            'unread' => $viewer === 'resident'
                ? $t->messages()->whereNull('read_by_resident_at')->count()
                : $t->messages()->whereNull('read_by_staff_at')->count(),
        ];
        if ($viewer !== 'resident') {
            $out['resident'] = ['name' => $t->resident->user->name, 'stand' => $t->resident->stand?->stand_number];
        }
        if ($detail) {
            $out['messages'] = $t->messages->map(fn (Message $m) => [
                'id' => $m->id,
                'from' => $m->sender_type,
                'from_label' => match ($m->sender_type) {
                    'resident' => $viewer === 'resident' ? 'You' : $t->resident->user->name,
                    'partner_user' => $t->partner?->name ?? 'Partner',
                    'committee' => 'Committee',
                    'assistant' => 'Assistant',
                    default => 'System',
                },
                'mine' => $viewer === 'resident' ? $m->sender_type === 'resident' : $m->sender_type !== 'resident',
                'body' => $m->body,
                'at' => $m->created_at->toIso8601String(),
            ]);
        }

        return $out;
    }
}
