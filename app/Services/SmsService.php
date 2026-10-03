<?php

namespace App\Services;

use App\Integrations\Sms\SmsGateway;
use App\Models\SmsLog;

class SmsService
{
    public function __construct(private SmsGateway $gateway) {}

    public function send(string $to, string $body, string $template = 'generic', bool $urgent = false): SmsLog
    {
        $log = SmsLog::create(['to' => $to, 'template' => $template, 'body' => $body, 'status' => 'queued']);
        $hour = (int) now()->format('G');
        $quiet = $hour >= config('fspra.sms.quiet_start') || $hour < config('fspra.sms.quiet_end');
        if ($quiet && ! $urgent && $template !== 'otp') {
            return $log; // dispatched later by the scheduler
        }
        $this->dispatch($log);

        return $log;
    }

    public function dispatch(SmsLog $log): void
    {
        try {
            $ref = $this->gateway->send($log->to, $log->body);
            $log->update(['status' => $ref ? 'sent' : 'failed', 'provider_reference' => $ref]);
        } catch (\Throwable $e) {
            report($e);
            $log->update(['status' => 'failed']);
        }
    }
}
