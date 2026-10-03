<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_new_resident_signs_up_with_otp(): void
    {
        $this->postJson('/api/auth/otp', ['phone' => '0779 999 888'])->assertOk();
        $code = cache('otp:last:+263779999888:login');
        $this->postJson('/api/auth/verify', ['phone' => '0779999888', 'code' => $code])->assertStatus(422)->assertJsonValidationErrors('name');

        $this->postJson('/api/auth/verify', ['phone' => '0779999888', 'code' => $code, 'name' => '<b>Tendai</b> Moyo', 'accept_terms' => true])
            ->assertOk()->assertJsonPath('user.name', 'Tendai Moyo');
        $this->assertAuthenticated();
        $this->assertTrue(User::where('phone', '+263779999888')->first()->hasRole('resident'));
    }

    public function test_wrong_code_is_rejected_and_locks_after_max_attempts(): void
    {
        $this->postJson('/api/auth/otp', ['phone' => '0771234567']);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/verify', ['phone' => '0771234567', 'code' => '000000'])->assertStatus(422);
        }
        $code = cache('otp:last:+263771234567:login');
        $this->postJson('/api/auth/verify', ['phone' => '0771234567', 'code' => $code])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_invalid_phone_rejected(): void
    {
        $this->postJson('/api/auth/otp', ['phone' => '12'])->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_otp_is_rate_limited(): void
    {
        RateLimiter::clear('otp-phone:0772222222');
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/otp', ['phone' => '0772222222'])->assertOk();
        }
        $this->postJson('/api/auth/otp', ['phone' => '0772222222'])->assertStatus(429);
    }

    public function test_guest_cannot_reach_resident_api(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/requests')->assertUnauthorized();
    }

    public function test_unverified_resident_is_blocked_from_services(): void
    {
        $u = $this->resident('1077', false);
        $this->actingAs($u)->getJson('/api/requests')->assertForbidden()->assertJson(['code' => 'verification_required']);
    }
}
