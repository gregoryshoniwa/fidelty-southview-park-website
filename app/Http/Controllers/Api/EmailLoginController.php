<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\MagicLinkMail;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Passwordless email sign-in with our own branded emails (sent from the association's mailbox). */
class EmailLoginController extends Controller
{
    public function request(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:120']]);
        $email = strtolower(trim($data['email']));
        $user = User::where('email', $email)->first();

        // Same response whether or not the address is registered (no account enumeration).
        $ok = response()->json(['sent' => true]);
        if ($user && ($user->isCommittee() || $user->partners()->exists() || ($user->status ?? 'active') !== 'active')) {
            return $ok;
        }

        DB::table('email_login_tokens')->where('email', $email)->whereNull('used_at')->update(['used_at' => now()]);
        $token = Str::random(64);
        DB::table('email_login_tokens')->insert([
            'email' => $email, 'token_hash' => hash('sha256', $token), 'user_id' => $user?->id,
            'expires_at' => now()->addMinutes(15), 'ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $url = url('/app/login/email?token='.$token);
        // Demo addresses use the reserved .test domain: locally the link is kept in the cache instead of emailed.
        if (! (app()->environment('local') && str_ends_with($email, '.test'))) {
            Mail::to($email)->send(new MagicLinkMail($url, ! $user));
        }
        if (app()->environment('local', 'testing')) {
            cache()->put('magic:last:'.$email, $token, 900);
        }

        return $ok;
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'name' => ['nullable', 'string', 'min:2', 'max:80'],
            'accept_terms' => ['nullable', 'boolean'],
        ]);
        $row = DB::table('email_login_tokens')->where('token_hash', hash('sha256', $data['token']))->first();
        if (! $row || $row->used_at || now()->greaterThan($row->expires_at)) {
            throw ValidationException::withMessages(['token' => 'This sign-in link has expired or was already used. Request a new one.']);
        }

        $user = User::where('email', $row->email)->first();
        if ($user && ($user->isCommittee() || $user->partners()->exists())) {
            throw ValidationException::withMessages(['token' => 'Committee and partner accounts sign in through their own portals.']);
        }
        if (! $user) {
            $name = strip_tags((string) ($data['name'] ?? ''));
            if (mb_strlen($name) < 2 || empty($data['accept_terms'])) {
                // Keep the link valid while the new resident adds their name.
                return response()->json(['needs_details' => true], 422);
            }
            $user = User::create(['name' => $name, 'email' => $row->email, 'email_verified_at' => now(), 'notification_prefs' => ['sms' => true, 'push' => true, 'email' => true]]);
            $user->assignRole('resident');
            $user->resident()->create([]);
            AuditLog::record('user.registered', $user, ['provider' => 'email_link'], $user);
        }
        if (($user->status ?? 'active') !== 'active') {
            throw ValidationException::withMessages(['token' => 'This account is suspended. Write to the committee.']);
        }

        DB::table('email_login_tokens')->where('id', $row->id)->update(['used_at' => now(), 'updated_at' => now()]);
        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now(), 'auth_provider' => $user->auth_provider ?? 'email_link', 'last_login_at' => now()])->save();
        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();
        AuditLog::record('user.login', $user, ['method' => 'email_link'], $user);

        return response()->json(['user' => Present::user($user->fresh())]);
    }
}
