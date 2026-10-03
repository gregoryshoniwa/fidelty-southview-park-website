<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\OtpService;
use App\Services\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Partner staff sign in with phone + password, then a one-time SMS code (two factors). */
class AuthController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function password(Request $request)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20'], 'password' => ['required', 'string', 'max:200']]);
        $phone = Phone::normalise($data['phone']);
        $user = $phone ? User::where('phone', $phone)->first() : null;
        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password) || ! $user->partners()->exists() || $user->status !== 'active') {
            AuditLog::record('partner.login_failed', null, ['phone' => $phone ? Phone::mask($phone) : null]);
            throw ValidationException::withMessages(['phone' => 'These details do not match a partner account.']);
        }
        $this->otp->issue($phone, 'partner_2fa');
        $request->session()->put('partner_2fa_user', $user->id);

        return response()->json(['otp_sent' => true, 'phone_masked' => Phone::mask($phone),
            'dev_code' => app()->environment('local') ? cache("otp:last:$phone:partner_2fa") : null]);
    }

    public function otp(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $uid = $request->session()->get('partner_2fa_user');
        $user = $uid ? User::find($uid) : null;
        if (! $user) {
            throw ValidationException::withMessages(['code' => 'Start again with your phone and password.']);
        }
        $this->otp->verify($user->phone, $data['code'], 'partner_2fa');
        $request->session()->forget('partner_2fa_user');
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_via', 'partner_2fa');
        $user->update(['last_login_at' => now()]);
        AuditLog::record('partner.login', $user, [], $user);

        return response()->json(['ok' => true]);
    }
}
