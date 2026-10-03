<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\OtpService;
use App\Services\Phone;
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
