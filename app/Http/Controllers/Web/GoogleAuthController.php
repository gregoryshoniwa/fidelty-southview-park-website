<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * "Continue with Google" through Google's own OAuth (Laravel Socialite), so the Google screen
 * names our domain. New residents are parked in the session until they give a name and accept the terms.
 */
class GoogleAuthController extends Controller
{
    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirect(Request $request)
    {
        abort_unless(self::enabled(), 404);
        $next = $request->query('next');
        $request->session()->put('google_next', is_string($next) && str_starts_with($next, '/') && ! str_starts_with($next, '//') ? $next : null);

        return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])->with(['prompt' => 'select_account'])->redirect();
    }

    public function callback(Request $request)
    {
        abort_unless(self::enabled(), 404);
        if ($request->filled('error')) {
            return $this->back('cancelled');
        }
        try {
            $g = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return $this->back('failed');
        }
        $email = $g->getEmail() && ($g->user['email_verified'] ?? false) ? strtolower($g->getEmail()) : null;
        if (! $email) {
            return $this->back('unverified');
        }

        $user = User::where('google_id', $g->getId())->first() ?? User::where('email', $email)->first();
        if ($user && ($user->isCommittee() || $user->partners()->exists())) {
            return $this->back('staff');
        }
        if (! $user) {
            $request->session()->put('google_pending', ['id' => $g->getId(), 'email' => $email, 'name' => $g->getName(), 'at' => now()->timestamp]);

            return redirect('/app/login?google=details');
        }
        if (($user->status ?? 'active') !== 'active') {
            return $this->back('suspended');
        }

        $user->forceFill(['google_id' => $user->google_id ?: $g->getId(), 'auth_provider' => 'google',
            'email_verified_at' => $user->email_verified_at ?: now(), 'last_login_at' => now()])->save();
        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();
        AuditLog::record('user.login', $user, ['method' => 'google'], $user);

        $next = $request->session()->pull('google_next');

        return redirect('/app'.($next ?: ($user->resident?->verification_status === 'verified' ? '/' : '/verify')));
    }

    /** Second step for a new resident: name and consent, then the parked Google identity becomes an account. */
    public function complete(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'accept_terms' => ['accepted'],
        ]);
        $p = $request->session()->get('google_pending');
        if (! $p || now()->timestamp - $p['at'] > 900) {
            $request->session()->forget('google_pending');
            throw ValidationException::withMessages(['name' => 'Your Google sign-in has expired. Press Continue with Google again.']);
        }
        if (User::where('google_id', $p['id'])->orWhere('email', $p['email'])->exists()) {
            $request->session()->forget('google_pending');
            throw ValidationException::withMessages(['name' => 'This Google account already has an account. Press Continue with Google again.']);
        }

        $user = User::create(['name' => strip_tags($data['name']), 'email' => $p['email'], 'email_verified_at' => now(),
            'notification_prefs' => ['sms' => true, 'push' => true]]);
        $user->forceFill(['google_id' => $p['id'], 'auth_provider' => 'google', 'last_login_at' => now()])->save();
        $user->assignRole('resident');
        $user->resident()->create([]);
        AuditLog::record('user.registered', $user, ['provider' => 'google'], $user);

        $request->session()->forget('google_pending');
        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();
        AuditLog::record('user.login', $user, ['method' => 'google'], $user);

        return response()->json(['user' => Present::user($user->fresh()), 'next' => $request->session()->pull('google_next')]);
    }

    /** Lets the login page prefill the name after Google sends a new resident back. */
    public function pending(Request $request)
    {
        $p = $request->session()->get('google_pending');

        return response()->json(['pending' => (bool) $p, 'name' => $p['name'] ?? null, 'email' => $p['email'] ?? null]);
    }

    private function back(string $reason)
    {
        return redirect('/app/login?google='.$reason);
    }
}
