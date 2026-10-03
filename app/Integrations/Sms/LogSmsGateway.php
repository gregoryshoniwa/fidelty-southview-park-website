<?php

namespace App\Integrations\Sms;

use Illuminate\Support\Facades\Log;

class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): ?string
    {
        $text = app()->isProduction() ? '['.strlen($message).' chars]' : $message;
        Log::channel('single')->info("[SMS to {$to}] {$text}");

        return 'log-'.uniqid();
    }
}
