<?php

namespace App\Integrations\Sms;

use Illuminate\Support\Facades\Log;

class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): ?string
    {
        Log::channel('single')->info("[SMS to {$to}] {$message}");

        return 'log-'.uniqid();
    }
}
