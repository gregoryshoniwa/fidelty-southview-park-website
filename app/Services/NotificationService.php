<?php

namespace App\Services;

use App\Models\InAppNotification;
use App\Models\User;

class NotificationService
{
    public function __construct(private SmsService $sms) {}

    public function notify(User $user, string $title, ?string $body = null, ?string $link = null, string $kind = 'info', bool $sms = true): InAppNotification
    {
        $n = InAppNotification::create(['user_id' => $user->id, 'title' => $title, 'body' => $body, 'link' => $link, 'kind' => $kind]);
        $prefs = $user->notification_prefs ?? [];
        if ($user->email && $user->email_verified_at && ($prefs['email'] ?? true)) {
            try {
                \Illuminate\Support\Facades\Mail::to($user->email)->queue(new \App\Mail\ResidentNotificationMail($user->name, $title, $body, $link ? url($link) : null));
            } catch (\Throwable $e) {
                report($e);
            }
        }
        if ($sms && ($prefs['sms'] ?? true) && $user->phone) {
            $text = $title.($body ? ': '.str($body)->limit(110) : '').' '.($link ? url($link) : '');
            $this->sms->send($user->phone, trim($text), 'notification');
        }

        return $n;
    }
}
