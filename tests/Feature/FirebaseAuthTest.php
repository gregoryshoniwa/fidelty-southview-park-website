<?php

namespace Tests\Feature;

use App\Integrations\Firebase\TokenVerifier;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FirebaseAuthTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private string $private = '';

    private string $public = '';

    protected function setUp(): void
    {
        parent::setUp();
        config(['fspra.firebase.project_id' => 'southview-test', 'fspra.firebase.api_key' => 'test-key']);
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $this->private);
        $this->public = openssl_pkey_get_details($key)['key'];
        $this->app->instance(TokenVerifier::class, new TokenVerifier(['kid1' => $this->public]));
    }

    private function token(array $claims = []): string
    {
        $now = time();

        return JWT::encode(array_merge([
            'iss' => 'https://securetoken.google.com/southview-test', 'aud' => 'southview-test', 'sub' => 'uid-'.uniqid(),
            'iat' => $now, 'exp' => $now + 3600, 'auth_time' => $now,
            'email' => 'tendai@example.com', 'email_verified' => true, 'name' => 'Tendai Moyo',
            'firebase' => ['sign_in_provider' => 'google.com'],
        ], $claims), $this->private, 'RS256', 'kid1');
    }

    public function test_config_exposes_providers_only_when_configured(): void
    {
        $this->getJson('/api/auth/config')->assertOk()->assertJsonPath('firebase.projectId', 'southview-test')->assertJsonPath('providers', ['email']);
        config(['fspra.firebase.project_id' => null]);
        $this->getJson('/api/auth/config')->assertOk()->assertJsonPath('firebase', null)->assertJsonPath('phone_provider', 'local');
    }

    public function test_google_sign_up_asks_for_terms_then_creates_resident(): void
    {
        $t = $this->token(['sub' => 'g-1']);
        $this->postJson('/api/auth/firebase', ['token' => $t])->assertStatus(422)->assertJson(['needs_details' => true, 'suggested_name' => 'Tendai Moyo']);
        $this->postJson('/api/auth/firebase', ['token' => $t, 'name' => 'Tendai Moyo', 'accept_terms' => true])->assertOk()->assertJsonPath('user.has_phone', false);
        $u = User::where('firebase_uid', 'g-1')->first();
        $this->assertSame('tendai@example.com', $u->email);
        $this->assertNotNull($u->email_verified_at);
        $this->assertTrue($u->hasRole('resident'));
        $this->assertAuthenticatedAs($u);
    }

    public function test_returning_user_matched_by_uid(): void
    {
        $t = $this->token(['sub' => 'g-2']);
        $this->postJson('/api/auth/firebase', ['token' => $t, 'name' => 'Rudo', 'accept_terms' => true])->assertOk();
        $this->postJson('/api/auth/logout');
        $this->flushSession();
        $this->postJson('/api/auth/firebase', ['token' => $this->token(['sub' => 'g-2'])])->assertOk();
        $this->assertSame(1, User::where('firebase_uid', 'g-2')->count());
    }

    public function test_unverified_email_password_account_is_refused(): void
    {
        $this->postJson('/api/auth/firebase', ['token' => $this->token(['email_verified' => false, 'firebase' => ['sign_in_provider' => 'password']])])->assertStatus(422);
    }

    public function test_firebase_phone_links_to_existing_sms_account(): void
    {
        $existing = $this->resident('1160');
        $this->postJson('/api/auth/firebase', ['token' => $this->token(['email' => null, 'email_verified' => false, 'phone_number' => $existing->phone, 'firebase' => ['sign_in_provider' => 'phone']])])->assertOk();
        $this->assertAuthenticatedAs($existing);
        $this->assertNotNull($existing->fresh()->firebase_uid);
    }

    public function test_tampered_wrong_project_and_stale_tokens_are_rejected(): void
    {
        $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($other, $otherPem);
        $forged = JWT::encode(['iss' => 'https://securetoken.google.com/southview-test', 'aud' => 'southview-test', 'sub' => 'x', 'iat' => time(), 'exp' => time() + 60, 'auth_time' => time()], $otherPem, 'RS256', 'kid1');
        $v = app(TokenVerifier::class);
        foreach ([$forged, $this->token(['aud' => 'someone-else']), $this->token(['auth_time' => time() - 3600])] as $bad) {
            try {
                $v->verify($bad);
                $this->fail('Token should have been rejected');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_staff_cannot_use_google_sign_in(): void
    {
        $this->postJson('/api/auth/firebase', ['token' => $this->token(['email' => 'admin@example.test'])])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_google_user_adds_phone_before_verifying_stand(): void
    {
        $this->postJson('/api/auth/firebase', ['token' => $this->token(['sub' => 'g-3', 'email' => 'nyasha@example.com']), 'name' => 'Nyasha', 'accept_terms' => true])->assertOk();
        $this->postJson('/api/verify/start', ['national_id' => '63-123456-A-12', 'stand_number' => '1101', 'consent' => true])->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->postJson('/api/me/phone/otp', ['phone' => '0775556666'])->assertOk();
        $code = cache('otp:last:+263775556666:link');
        $this->postJson('/api/me/phone', ['code' => $code])->assertOk()->assertJsonPath('user.has_phone', true);
        $this->postJson('/api/verify/start', ['national_id' => '63-123456-A-12', 'stand_number' => '1101', 'consent' => true])->assertOk();
    }
}
