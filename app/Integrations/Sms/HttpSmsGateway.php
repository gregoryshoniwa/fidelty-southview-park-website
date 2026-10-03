<?php

namespace App\Integrations\Sms;

use Illuminate\Support\Facades\Http;

/** Generic bulk SMS adapter (Econet/NetOne A2P aggregator). Adjust payload to the chosen provider. */
class HttpSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): ?string
    {
        $res = Http::withToken((string) config('fspra.sms.key'))->timeout(10)
            ->post((string) config('fspra.sms.url'), ['to' => $to, 'from' => \App\Models\Setting::get('sms_sender_id', config('fspra.sms.sender_id')), 'message' => $message]);

        return $res->successful() ? (string) ($res->json('id') ?? 'ok') : null;
    }
}
