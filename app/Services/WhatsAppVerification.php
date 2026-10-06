<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PhoneChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Free phone verification: the resident sends "VERIFY 123456" from their own WhatsApp to the
 * association's number. Meta's webhook tells us which number sent it, which proves they hold that phone.
 * Incoming messages cost nothing on the WhatsApp Cloud API.
 */
class WhatsAppVerification
{
    public const TTL_MINUTES = 10;

    public static function enabled(): bool
    {
        $c = config('fspra.whatsapp');

        return filled($c['number']) && filled($c['verify_token']) && filled($c['app_secret']);
    }

    public function start(string $purpose, ?User $user): PhoneChallenge
    {
        PhoneChallenge::where('expires_at', '<', now()->subDay())->delete();
        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (PhoneChallenge::where('code', $code)->where('status', 'pending')->where('expires_at', '>', now())->exists());

        return PhoneChallenge::create(['code' => $code, 'purpose' => $purpose, 'user_id' => $user?->id,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)]);
    }

    public function link(string $code): string
    {
        $number = preg_replace('/\D+/', '', (string) config('fspra.whatsapp.number'));

        return 'https://wa.me/'.$number.'?text='.rawurlencode('VERIFY '.$code);
    }

    /** Called for each incoming WhatsApp text. Returns the matched challenge, if any. */
    public function receive(string $from, string $text): ?PhoneChallenge
    {
        if (! preg_match('/\b(\d{6})\b/', $text, $m)) {
            return null;
        }
        $phone = Phone::normalise($from);
        $challenge = PhoneChallenge::where('code', $m[1])->where('status', 'pending')->where('expires_at', '>', now())->first();
        if (! $challenge) {
            return null;
        }
        if (! $phone) {
            Log::info('whatsapp verify from non-Zimbabwean number ignored');

            return null;
        }
        $challenge->update(['status' => 'verified', 'phone' => $phone, 'verified_at' => now()]);
        $this->reply($from, 'Thank you. Your number is confirmed. Go back to the Southview Park website to continue.');

        return $challenge;
    }

    /**
     * Give the proved number to the account. A number someone only typed (unconfirmed) on another
     * account is released; a number already proved by another account is refused.
     */
    public function attach(User $user, string $phone): void
    {
        DB::transaction(function () use ($user, $phone) {
            if (User::where('phone', $phone)->where('id', '!=', $user->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['phone' => 'This number is already linked to another account. Write to the committee if it is yours.']);
            }
            User::where('unconfirmed_phone', $phone)->where('id', '!=', $user->id)->update(['unconfirmed_phone' => null]);
            $user->forceFill(['phone' => $phone, 'phone_verified_at' => now(), 'unconfirmed_phone' => null])->save();
        });
        AuditLog::record('user.phone_linked', $user, ['method' => 'whatsapp'], $user);
    }

    /** Optional thank-you reply; free because the resident wrote first. Needs WHATSAPP_TOKEN and WHATSAPP_PHONE_NUMBER_ID. */
    private function reply(string $to, string $text): void
    {
        $token = config('fspra.whatsapp.token');
        $id = config('fspra.whatsapp.phone_number_id');
        if (! $token || ! $id) {
            return;
        }
        try {
            Http::withToken($token)->timeout(5)->post("https://graph.facebook.com/v21.0/{$id}/messages", [
                'messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'text', 'text' => ['body' => $text],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
