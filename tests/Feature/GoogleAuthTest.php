<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'cid.apps.googleusercontent.com', 'services.google.client_secret' => 'secret']);
    }

    private function google(array $raw = []): void
    {
        $raw = array_merge(['sub' => 'g-100', 'email' => 'tendai@example.com', 'email_verified' => true, 'name' => 'Tendai Moyo'], $raw);
        $u = (new GoogleUser)->setRaw($raw)->map(['id' => $raw['sub'], 'email' => $raw['email'], 'name' => $raw['name']]);
        Socialite::shouldReceive('driver->user')->andReturn($u);
    }

    public function test_config_lists_google_only_when_configured(): void
    {
        $this->getJson('/api/auth/config')->assertJsonPath('providers', ['google', 'email']);
        config(['services.google.client_id' => null]);
        $this->getJson('/api/auth/config')->assertJsonPath('providers', ['email']);
        $this->get('/auth/google')->assertNotFound();
    }

    public function test_redirect_goes_to_google_with_our_callback(): void
    {
        $location = $this->get('/auth/google?next=/requests')->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth', $location);
        $this->assertStringContainsString(urlencode(url('/auth/google/callback')), $location);
        $this->assertStringContainsString('state=', $location);
    }

    public function test_new_resident_gives_name_and_terms_then_gets_an_account(): void
    {
        $this->google();
        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/app/login?google=details');
        $this->assertGuest();
        $this->getJson('/api/auth/google/pending')->assertJson(['pending' => true, 'name' => 'Tendai Moyo', 'email' => 'tendai@example.com']);

        $this->postJson('/api/auth/google/complete', ['name' => 'Tendai Moyo', 'accept_terms' => false])->assertStatus(422)->assertJsonValidationErrors('accept_terms');
        $this->postJson('/api/auth/google/complete', ['name' => 'Tendai Moyo', 'accept_terms' => true])->assertOk()->assertJsonPath('user.name', 'Tendai Moyo');

        $u = User::where('google_id', 'g-100')->firstOrFail();
        $this->assertSame('tendai@example.com', $u->email);
        $this->assertTrue($u->hasRole('resident'));
        $this->assertAuthenticatedAs($u);
        $this->getJson('/api/auth/google/pending')->assertJson(['pending' => false]);
    }

    public function test_existing_account_is_matched_by_email_and_signed_in(): void
    {
        $existing = User::create(['name' => 'Rudo', 'email' => 'rudo@example.com', 'email_verified_at' => now()]);
        $existing->assignRole('resident');
        $existing->resident()->create([]);
        $this->google(['sub' => 'g-200', 'email' => 'Rudo@Example.com']);

        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/app/verify');
        $this->assertAuthenticatedAs($existing);
        $this->assertSame('g-200', $existing->fresh()->google_id);
    }

    public function test_unverified_google_email_is_refused(): void
    {
        $this->google(['email_verified' => false]);
        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/app/login?google=unverified');
        $this->assertGuest();
    }

    public function test_committee_accounts_cannot_use_google(): void
    {
        $chair = User::create(['name' => 'Chair', 'email' => 'chair@example.com', 'email_verified_at' => now()]);
        $chair->assignRole('committee');
        $this->google(['email' => 'chair@example.com']);
        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/app/login?google=staff');
        $this->assertGuest();
    }

    public function test_cancel_and_missing_pending_are_handled(): void
    {
        $this->get('/auth/google/callback?error=access_denied')->assertRedirect('/app/login?google=cancelled');
        $this->postJson('/api/auth/google/complete', ['name' => 'Someone', 'accept_terms' => true])->assertStatus(422)->assertJsonValidationErrors('name');
    }
}
