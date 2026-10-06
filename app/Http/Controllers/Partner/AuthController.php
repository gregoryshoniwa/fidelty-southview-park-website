<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Mail\LoginCodeMail;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/** Partner staff sign in with email + password, then a one-time code sent to that email (two factors, no SMS cost). */
class AuthController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function password(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255'], 'password' => ['required', 'string', 'max:200']]);
        $email = strtolower(trim($data['email']));
        $user = User::where('email', $email)->first();
        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password) || ! $user->partners()->exists() || $user->status !== 'active') {
            AuditLog::record('partner.login_failed', null, ['email' => self::mask($email)]);
            throw ValidationException::withMessages(['email' => 'These details do not match a partner account.']);
        }
        $key = 'p'.$user->id;
        [, $code] = $this->otp->create($key, 'partner_2fa');
        // Demo accounts use the reserved .test domain; locally the code is shown on screen instead.
        if (! (app()->environment('local') && str_ends_with($user->email, '.test'))) {
            Mail::to($user->email)->send(new LoginCodeMail($code, $user->name));
        }
        $request->session()->put('partner_2fa_user', $user->id);

        return response()->json(['otp_sent' => true, 'email_masked' => self::mask($user->email),
            'dev_code' => app()->environment('local') ? $code : null]);
    }

    public function otp(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $uid = $request->session()->get('partner_2fa_user');
        $user = $uid ? User::find($uid) : null;
        if (! $user) {
            throw ValidationException::withMessages(['code' => 'Start again with your email and password.']);
        }
        $this->otp->verify('p'.$user->id, $data['code'], 'partner_2fa');
        $request->session()->forget('partner_2fa_user');
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_via', 'partner_2fa');
        $user->update(['last_login_at' => now()]);
        AuditLog::record('partner.login', $user, [], $user);

        return response()->json(['ok' => true]);
    }

    /** "gregory@bank.co.zw" -> "g••••••@bank.co.zw" */
    private static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).str_repeat('•', max(2, mb_strlen($local) - 1)).'@'.$domain;
    }
}
