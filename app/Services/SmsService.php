<?php

namespace App\Services;

use App\Integrations\Sms\SmsGateway;
use App\Models\SmsLog;

class SmsService
{
    public function __construct(private SmsGateway $gateway) {}

    public function send(string $to, string $body, string $template = 'generic', bool $urgent = false): SmsLog
    {
        // OTP codes are never stored: the log keeps a redacted copy only.
        $stored = $template === 'otp' ? preg_replace('/\d{6}/', '[code]', $body) : $body;
        $log = SmsLog::create(['to' => $to, 'template' => $template, 'body' => $stored, 'status' => 'queued']);
        $cap = (int) config('fspra.sms.daily_cap');
        if ($cap && SmsLog::whereDate('created_at', today())->whereIn('status', ['sent', 'queued'])->count() > $cap) {
            $log->update(['status' => 'capped']);
            report(new \RuntimeException('Daily SMS cap reached'));

            return $log;
        }
        if ($template === 'otp') {
            $this->deliver($log, $body);

            return $log;
        }
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
        $this->deliver($log, $log->body);
    }

    private function deliver(SmsLog $log, string $body): void
    {
        try {
            $ref = $this->gateway->send($log->to, $body);
            $log->update(['status' => $ref ? 'sent' : 'failed', 'provider_reference' => $ref]);
        } catch (\Throwable $e) {
            report($e);
            $log->update(['status' => 'failed']);
        }
    }
}
