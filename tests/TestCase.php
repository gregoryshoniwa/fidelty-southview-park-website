<?php

namespace Tests;

use App\Models\Partner;
use App\Models\Resident;
use App\Models\Stand;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Same-origin SPA requests carry a Referer; Sanctum uses it to start the session.
        $this->withHeader('Referer', config('app.url'));
    }

    /** Switching users in one test = a new browser session, as in real life. */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if (auth()->user()?->getAuthIdentifier() !== $user->getAuthIdentifier()) {
            $this->flushSession();
            $this->app['auth']->forgetGuards();
        }

        parent::actingAs($user, $guard);
        if ($user instanceof User && $user->partners()->exists()) {
            $this->withSession(['auth_via' => 'partner_2fa']);
        }

        return $this;
    }

    protected function resident(string $stand = '1050', bool $verified = true): User
    {
        $u = User::create(['name' => 'Test Resident '.$stand, 'phone' => '+26377'.str_pad($stand, 7, '0', STR_PAD_LEFT), 'phone_verified_at' => now()]);
        $u->assignRole($verified ? ['resident', 'verified_resident'] : ['resident']);
        $s = Stand::firstOrCreate(['stand_number' => $stand]);
        Resident::create([
            'user_id' => $u->id, 'stand_id' => $verified ? $s->id : null,
            'verification_status' => $verified ? 'verified' : 'unverified', 'verified_at' => $verified ? now() : null,
            'fidelity_reference' => $verified ? 'FL-TEST'.$stand : null,
        ]);

        return $u->fresh();
    }

    protected function partnerUser(string $slug): User
    {
        $u = User::create(['name' => 'Staff '.$slug, 'phone' => '+26378'.random_int(1000000, 9999999), 'password' => 'Secret-pass-123', 'phone_verified_at' => now()]);
        $u->assignRole('partner_user');
        Partner::where('slug', $slug)->first()->users()->attach($u->id, ['role' => 'admin']);

        return $u;
    }
}
