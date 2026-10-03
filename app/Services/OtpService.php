<?php

namespace App\Services;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function __construct(private SmsService $sms) {}

    public function issue(string $phone, string $purpose = 'login', ?string $sendTo = null): OtpCode
    {
        OtpCode::where('phone', $phone)->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        $code = str_pad((string) random_int(0, 10 ** config('fspra.otp.length') - 1), config('fspra.otp.length'), '0', STR_PAD_LEFT);

        $otp = OtpCode::create([
            'phone' => $phone,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(config('fspra.otp.ttl_minutes')),
            'ip' => request()?->ip(),
        ]);

        $label = $purpose === 'verify' ? 'stand verification' : 'sign-in';
        $this->sms->send($sendTo ?? $phone, "Your Southview Park {$label} code is {$code}. It expires in ".config('fspra.otp.ttl_minutes').' minutes. Never share it.', 'otp', true);

        if (app()->environment('local', 'testing')) {
            cache()->put("otp:last:$phone:$purpose", $code, 600);
        }

        return $otp;
    }

    public function verify(string $phone, string $code, string $purpose = 'login'): void
    {
        $otp = OtpCode::where('phone', $phone)->where('purpose', $purpose)->whereNull('consumed_at')->latest('id')->first();

        if (! $otp || $otp->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'This code has expired. Request a new one.']);
        }
        if ($otp->attempts >= config('fspra.otp.max_attempts')) {
            $otp->update(['consumed_at' => now()]);
            throw ValidationException::withMessages(['code' => 'Too many attempts. Request a new code.']);
        }
        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            throw ValidationException::withMessages(['code' => 'That code is not correct.']);
        }
        $otp->update(['consumed_at' => now()]);
    }
}
