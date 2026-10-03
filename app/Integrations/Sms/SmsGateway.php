<?php

namespace App\Integrations\Sms;

interface SmsGateway
{
    public function send(string $to, string $message): ?string;
}
