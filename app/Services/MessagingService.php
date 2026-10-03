<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Partner;
use App\Models\Resident;
use App\Models\Thread;

class MessagingService
{
    public function __construct(private NotificationService $notify) {}

    public function openThread(Resident $resident, string $subject, string $body, ?Partner $partner = null, ?int $requestId = null, string $category = 'general'): Thread
    {
        $thread = Thread::create([
            'reference' => Reference::next($partner ? 'MSG' : 'INB', 'threads'),
            'resident_id' => $resident->id,
            'partner_id' => $partner?->id,
            'service_request_id' => $requestId,
            'subject' => $subject,
            'category' => $category,
            'status' => 'open',
            'last_message_at' => now(),
        ]);
        $this->post($thread, 'resident', $resident->user_id, $body);

        return $thread;
    }

    public function post(Thread $thread, string $senderType, ?int $senderId, string $body, array $attachments = []): Message
    {
        $msg = $thread->messages()->create([
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'body' => $body,
            'attachments' => $attachments ?: null,
            $senderType === 'resident' ? 'read_by_resident_at' : 'read_by_staff_at' => now(),
        ]);

        $update = ['last_message_at' => now()];
        if ($senderType === 'resident') {
            $update['status'] = 'open';
        } else {
            $update['status'] = 'answered';
            if (! $thread->first_response_at) {
                $update['first_response_at'] = now();
            }
            $from = $thread->partner?->name ?? 'The committee';
            $user = $thread->resident->user;
            $this->notify->notify($user, "New message from {$from}", str($body)->limit(120)->toString(), '/app/inbox/'.$thread->reference, 'message');
        }
        $thread->update($update);

        if ($senderType === 'resident' && $thread->partner) {
            foreach ($thread->partner->users as $staff) {
                $this->notify->notify($staff, 'Resident replied on '.$thread->reference, str($body)->limit(120)->toString(), '/partner/messages/'.$thread->reference, 'message', false);
            }
        }

        return $msg;
    }

    public function senderName(Message $m): string
    {
        return match ($m->sender_type) {
            'resident' => 'You',
            'partner_user' => $m->thread->partner?->name ?? 'Partner',
            'committee' => 'Committee',
            'assistant' => 'Assistant',
            default => 'System',
        };
    }
}
