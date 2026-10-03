<?php

namespace App\Services;

use App\Integrations\Fidelity\FidelityClient;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\Resident;
use App\Models\Stand;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class VerificationService
{
    public function __construct(private FidelityClient $fidelity, private OtpService $otp) {}

    public static function hashId(string $nationalId): string
    {
        $norm = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $nationalId));

        return hash_hmac('sha256', $norm, (string) config('fspra.id_hash_salt'));
    }

    public static function otpKey(User $user): string
    {
        return $user->phone ?? 'user:'.$user->id;
    }

    public function start(User $user, string $nationalId, string $standNumber): Resident
    {
        if (! $user->phone) {
            throw ValidationException::withMessages(['phone' => 'Add your mobile number first, so we can send you updates.']);
        }
        $resident = $user->resident()->firstOrCreate([]);
        Consent::create([
            'user_id' => $user->id, 'purpose' => 'fidelity_verification', 'text_version' => config('fspra.consent_version'),
            'given_at' => now(), 'ip' => request()->ip(), 'user_agent' => substr((string) request()->userAgent(), 0, 255),
        ]);
        $resident->consent_fidelity_at = now();

        $standNumber = strtoupper(trim($standNumber));
        $result = $this->fidelity->match($nationalId, $standNumber);
        $manual = (bool) ($result['manual'] ?? false);
        AuditLog::record('verification.match_attempt', $resident, ['stand' => $standNumber, 'matched' => $result['matched']]);

        if (! $result['matched']) {
            $resident->save();
            throw ValidationException::withMessages(['stand_number' => 'We could not match this ID and stand number with Fidelity Life records. Check both and try again, or write to the committee.']);
        }

        $stand = Stand::firstOrCreate(['stand_number' => $standNumber], ['source' => 'fidelity_match']);
        $taken = Resident::where('stand_id', $stand->id)->where('verification_status', 'verified')->where('id', '!=', $resident->id)->exists();
        if ($taken) {
            throw ValidationException::withMessages(['stand_number' => 'This stand is already verified to another account. Write to the committee if you think this is wrong.']);
        }

        $resident->fill([
            'stand_id' => $stand->id,
            'national_id_hash' => self::hashId($nationalId),
            'national_id_last4' => substr(preg_replace('/[^0-9A-Za-z]/', '', $nationalId), -4),
            'verification_status' => $manual ? 'review' : 'pending',
            'fidelity_reference' => $result['reference'],
            'phone_on_file_masked' => $result['phone_masked'] ?? null,
        ])->save();

        if ($manual) {
            AuditLog::record('verification.manual_review_requested', $resident);

            return $resident;
        }
        $this->otp->issue(self::otpKey($user), 'verify', $result['phone'] ?? $user->phone);

        return $resident;
    }

    public function confirm(User $user, string $code): Resident
    {
        $resident = $user->resident;
        if (! $resident || $resident->verification_status !== 'pending') {
            throw ValidationException::withMessages(['code' => 'Start verification first.']);
        }
        $this->otp->verify(self::otpKey($user), $code, 'verify');
        $resident->update(['verification_status' => 'verified', 'verified_at' => now()]);
        $user->assignRole('verified_resident');
        AuditLog::record('verification.completed', $resident);

        return $resident;
    }
}
