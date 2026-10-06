<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PhoneChallenge;
use App\Models\User;
use App\Services\Phone;
use App\Services\WhatsAppVerification;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Phone numbers without paid SMS: WhatsApp proof, or a number the committee confirms. */
class PhoneVerifyController extends Controller
{
    public function __construct(private WhatsAppVerification $wa) {}

    /** Signed-in resident adds or proves their number. */
    public function startLink(Request $request)
    {
        return $this->start($request, 'link', $request->user());
    }

    /** Guest signs in (or signs up) by proving their number. */
    public function startLogin(Request $request)
    {
        return $this->start($request, 'login', null);
    }

    private function start(Request $request, string $purpose, ?User $user)
    {
        abort_unless(WhatsAppVerification::enabled(), 404);
        $c = $this->wa->start($purpose, $user);
        $request->session()->put('wa_challenge', $c->id);

        return response()->json([
            'id' => $c->id, 'code' => $c->code, 'link' => $this->wa->link($c->code),
            'number' => '+'.preg_replace('/\D+/', '', (string) config('fspra.whatsapp.number')),
            'expires_at' => $c->expires_at->toIso8601String(),
        ]);
    }

    /** The page polls this until the WhatsApp message arrives. */
    public function status(Request $request, string $id)
    {
        $c = $this->owned($request, $id);
        if ($c->status === 'pending') {
            return response()->json(['status' => $c->isLive() ? 'pending' : 'expired']);
        }
        if ($c->status !== 'verified') {
            return response()->json(['status' => 'expired']);
        }

        if ($c->purpose === 'link') {
            abort_unless($request->user()?->id === $c->user_id, 403);
            $this->wa->attach($request->user(), $c->phone);
            $c->update(['status' => 'used']);

            return response()->json(['status' => 'done', 'user' => Present::user($request->user()->fresh())]);
        }

        $user = User::where('phone', $c->phone)->first();
        if (! $user) {
            return response()->json(['status' => 'needs_details', 'phone_masked' => Phone::mask($c->phone)]);
        }

        return $this->signIn($request, $c, $user);
    }

    /** New resident after WhatsApp proof: name and terms, then the account is created. */
    public function complete(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:80'], 'accept_terms' => ['accepted']]);
        $c = $this->owned($request, (string) $request->session()->get('wa_challenge'));
        if ($c->purpose !== 'login' || $c->status !== 'verified' || $c->verified_at->lt(now()->subMinutes(15))) {
            throw ValidationException::withMessages(['name' => 'This WhatsApp confirmation has expired. Start again.']);
        }
        $user = User::where('phone', $c->phone)->first();
        if (! $user) {
            $user = User::create(['name' => strip_tags($data['name']), 'phone' => $c->phone, 'phone_verified_at' => now(),
                'notification_prefs' => ['sms' => true, 'push' => true]]);
            $user->assignRole('resident');
            $user->resident()->create([]);
            User::where('unconfirmed_phone', $c->phone)->update(['unconfirmed_phone' => null]);
            AuditLog::record('user.registered', $user, ['provider' => 'whatsapp'], $user);
        }

        return $this->signIn($request, $c, $user);
    }

    /** No SMS and no WhatsApp: keep the number as unconfirmed until the committee checks it. */
    public function unconfirmed(Request $request)
    {
        abort_unless(config('fspra.phone_provider') === 'none', 404);
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = Phone::normalise($data['phone']) ?? throw ValidationException::withMessages(['phone' => 'Enter a valid Zimbabwean mobile number.']);
        if (User::where('phone', $phone)->where('id', '!=', $request->user()->id)->exists()) {
            throw ValidationException::withMessages(['phone' => 'This number is already linked to another account. Write to the committee if it is yours.']);
        }
        $request->user()->forceFill(['unconfirmed_phone' => $phone])->save();
        AuditLog::record('user.phone_unconfirmed', $request->user());

        return response()->json(['user' => Present::user($request->user()->fresh())]);
    }

    private function owned(Request $request, string $id): PhoneChallenge
    {
        abort_unless($id !== '' && hash_equals((string) $request->session()->get('wa_challenge'), $id), 404);

        return PhoneChallenge::findOrFail($id);
    }

    private function signIn(Request $request, PhoneChallenge $c, User $user)
    {
        if ($user->isCommittee() || $user->partners()->exists()) {
            $c->update(['status' => 'used']);
            throw ValidationException::withMessages(['phone' => 'Committee and partner accounts sign in through their own portals.']);
        }
        if (($user->status ?? 'active') !== 'active') {
            throw ValidationException::withMessages(['phone' => 'This account is suspended. Write to the committee.']);
        }
        $c->update(['status' => 'used']);
        Auth::guard('web')->login($user, true);
        $request->session()->forget('wa_challenge');
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);
        AuditLog::record('user.login', $user, ['method' => 'whatsapp'], $user);

        return response()->json(['status' => 'done', 'user' => Present::user($user->fresh())]);
    }
}
