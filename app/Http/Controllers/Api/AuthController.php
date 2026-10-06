<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\GoogleAuthController;
use App\Integrations\Firebase\TokenVerifier;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\OtpService;
use App\Services\Phone;
use App\Services\WhatsAppVerification;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function requestOtp(Request $request)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = Phone::normalise($data['phone']) ?? throw ValidationException::withMessages(['phone' => 'Enter a valid Zimbabwean mobile number, for example 077 123 4567.']);
        $this->otp->issue($phone, 'login');

        return response()->json([
            'sent' => true,
            'phone_masked' => Phone::mask($phone),
            'dev_code' => app()->environment('local') ? cache("otp:last:$phone:login") : null,
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'digits:6'],
            'name' => ['nullable', 'string', 'min:2', 'max:80'],
            'accept_terms' => ['nullable', 'boolean'],
        ]);
        $phone = Phone::normalise($data['phone']) ?? throw ValidationException::withMessages(['phone' => 'Invalid number.']);
        $user = User::where('phone', $phone)->first();
        $needsDetails = ! $user && (empty($data['name']) || empty($data['accept_terms']));
        // Check the code first; keep it valid while a new resident adds their name.
        $this->otp->verify($phone, $data['code'], 'login', consume: ! $needsDetails);
        if ($needsDetails) {
            throw ValidationException::withMessages(['name' => 'Enter your name and accept the terms to create your account.']);
        }
        if (! $user) {
            $user = User::create(['name' => strip_tags($data['name']), 'phone' => $phone, 'phone_verified_at' => now(), 'notification_prefs' => ['sms' => true, 'push' => true]]);
            $user->assignRole('resident');
            $user->resident()->create([]);
            AuditLog::record('user.registered', $user, [], $user);
        }
        if ($user->isCommittee() || $user->partners()->exists()) {
            throw ValidationException::withMessages(['phone' => 'Committee and partner accounts sign in through their own portals.']);
        }
        if (($user->status ?? 'active') !== 'active') {
            throw ValidationException::withMessages(['phone' => 'This account is suspended. Write to the committee.']);
        }

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);
        AuditLog::record('user.login', $user, ['method' => 'otp'], $user);

        return response()->json(['user' => Present::user($user->fresh())]);
    }

    /** Public sign-in options for the app. Firebase web config is public by design. */
    public function config()
    {
        $f = config('fspra.firebase');
        $enabled = filled($f['project_id']) && filled($f['api_key']);

        return response()->json([
            'firebase' => $enabled ? ['apiKey' => $f['api_key'], 'authDomain' => $f['auth_domain'] ?: $f['project_id'].'.firebaseapp.com', 'projectId' => $f['project_id'], 'appId' => $f['app_id']] : null,
            'providers' => array_values(array_filter([GoogleAuthController::enabled() ? 'google' : null, 'email'])),
            'email_via' => 'association',
            'phone_provider' => match (true) {
                $enabled && config('fspra.phone_provider') === 'firebase' => 'firebase',
                config('fspra.phone_provider') === 'none' => 'none',
                default => 'local',
            },
            'whatsapp' => WhatsAppVerification::enabled(),
        ]);
    }

    /** Google, email-link or Firebase phone sign-in: exchange a verified Firebase ID token for a session. */
    public function firebase(Request $request, TokenVerifier $verifier)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'name' => ['nullable', 'string', 'min:2', 'max:80'],
            'accept_terms' => ['nullable', 'boolean'],
        ]);
        $claims = $verifier->verify($data['token']);
        $provider = $claims['firebase']['sign_in_provider'] ?? 'unknown';
        $email = isset($claims['email']) && ($claims['email_verified'] ?? false) ? strtolower($claims['email']) : null;
        $phone = isset($claims['phone_number']) ? Phone::normalise($claims['phone_number']) : null;
        if ($provider === 'password' && ! $email) {
            throw ValidationException::withMessages(['token' => 'Confirm your email address first, using the link we sent you.']);
        }
        if (! $email && ! $phone) {
            throw ValidationException::withMessages(['token' => 'This sign-in method did not give us a verified email or Zimbabwean phone number.']);
        }

        $user = User::where('firebase_uid', $claims['sub'])->first()
            ?? ($phone ? User::where('phone', $phone)->first() : null)
            ?? ($email ? User::where('email', $email)->first() : null);

        if ($user && ($user->isCommittee() || $user->partners()->exists())) {
            throw ValidationException::withMessages(['token' => 'Committee and partner accounts sign in through their own portals.']);
        }
        if (! $user) {
            $name = strip_tags((string) ($data['name'] ?? $claims['name'] ?? ''));
            if (mb_strlen($name) < 2 || empty($data['accept_terms'])) {
                return response()->json(['needs_details' => true, 'suggested_name' => $claims['name'] ?? null], 422);
            }
            $user = User::create(['name' => $name, 'phone' => $phone, 'email' => $email, 'email_verified_at' => $email ? now() : null,
                'phone_verified_at' => $phone ? now() : null, 'notification_prefs' => ['sms' => true, 'push' => true]]);
            $user->assignRole('resident');
            $user->resident()->create([]);
            AuditLog::record('user.registered', $user, ['provider' => $provider], $user);
        }
        if (($user->status ?? 'active') !== 'active') {
            throw ValidationException::withMessages(['token' => 'This account is suspended. Write to the committee.']);
        }
        $user->forceFill(array_filter([
            'firebase_uid' => $user->firebase_uid ?: $claims['sub'],
            'auth_provider' => $provider,
            'email' => $user->email ?: $email,
            'email_verified_at' => $user->email_verified_at ?: ($email ? now() : null),
            'phone' => $user->phone ?: $phone,
            'phone_verified_at' => $user->phone_verified_at ?: ($phone ? now() : null),
            'last_login_at' => now(),
        ], fn ($v) => $v !== null))->save();

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();
        AuditLog::record('user.login', $user, ['method' => 'firebase:'.$provider], $user);

        return response()->json(['user' => Present::user($user->fresh())]);
    }

    /** Add or change the account's mobile number (needed for SMS updates and stand verification). */
    public function linkPhoneOtp(Request $request)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = Phone::normalise($data['phone']) ?? throw ValidationException::withMessages(['phone' => 'Enter a valid Zimbabwean mobile number.']);
        if (User::where('phone', $phone)->where('id', '!=', $request->user()->id)->exists()) {
            throw ValidationException::withMessages(['phone' => 'This number is already linked to another account.']);
        }
        $this->otp->issue($phone, 'link');
        $request->session()->put('link_phone', $phone);

        return response()->json(['sent' => true, 'phone_masked' => Phone::mask($phone), 'dev_code' => app()->environment('local') ? cache("otp:last:$phone:link") : null]);
    }

    public function linkPhone(Request $request, TokenVerifier $verifier)
    {
        $data = $request->validate(['code' => ['nullable', 'digits:6'], 'token' => ['nullable', 'string', 'max:4096']]);
        if (! empty($data['token'])) {
            $claims = $verifier->verify($data['token']);
            $phone = Phone::normalise($claims['phone_number'] ?? null) ?? throw ValidationException::withMessages(['phone' => 'That phone sign-in did not return a Zimbabwean number.']);
        } else {
            $phone = $request->session()->get('link_phone') ?? throw ValidationException::withMessages(['code' => 'Request a code first.']);
            $this->otp->verify($phone, (string) ($data['code'] ?? ''), 'link');
        }
        if (User::where('phone', $phone)->where('id', '!=', $request->user()->id)->exists()) {
            throw ValidationException::withMessages(['phone' => 'This number is already linked to another account.']);
        }
        $request->user()->forceFill(['phone' => $phone, 'phone_verified_at' => now()])->save();
        $request->session()->forget('link_phone');
        AuditLog::record('user.phone_linked', $request->user());

        return response()->json(['user' => Present::user($request->user()->fresh())]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => Present::user($request->user())]);
    }

    public function logout(Request $request)
    {
        AuditLog::record('user.logout', $request->user());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }
}
