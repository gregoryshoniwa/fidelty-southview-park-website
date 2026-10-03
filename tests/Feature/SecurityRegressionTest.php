<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_staff_cannot_sign_in_with_resident_sms_code(): void
    {
        $admin = User::where('email', 'admin@example.test')->first();
        $this->postJson('/api/auth/otp', ['phone' => $admin->phone])->assertOk();
        $code = cache('otp:last:'.$admin->phone.':login');
        $this->postJson('/api/auth/verify', ['phone' => $admin->phone, 'code' => $code])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_partner_api_requires_the_two_factor_session(): void
    {
        $staff = $this->partnerUser('marufu-attorneys');
        $this->flushSession();
        $this->be($staff);
        $this->getJson('/api/partner/me')->assertUnauthorized();
    }

    public function test_otp_response_does_not_reveal_registered_numbers(): void
    {
        $this->postJson('/api/auth/otp', ['phone' => '0771234567'])->assertOk()->assertJsonMissingPath('new_account');
    }

    public function test_foreign_numbers_are_rejected(): void
    {
        $this->postJson('/api/auth/otp', ['phone' => '+447700900123'])->assertStatus(422);
        $this->postJson('/api/auth/otp', ['phone' => '+2348012345678'])->assertStatus(422);
    }

    public function test_otp_codes_are_not_stored(): void
    {
        $this->postJson('/api/auth/otp', ['phone' => '0773334444']);
        $code = cache('otp:last:+263773334444:login');
        $this->assertFalse(SmsLog::where('body', 'like', "%{$code}%")->exists());
        $this->assertTrue(SmsLog::where('body', 'like', '%[code]%')->exists());
    }

    public function test_webhook_with_weak_secret_is_refused(): void
    {
        config(['fspra.tncb.webhook_secret' => 'short']);
        $raw = json_encode(['reference' => 'x', 'status' => 'paid', 'amount' => '1.00', 'currency' => 'USD']);
        $this->call('POST', '/webhooks/tncb', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => hash_hmac('sha256', $raw, 'short')], $raw)->assertUnauthorized();
    }

    public function test_manual_verification_waits_for_committee(): void
    {
        config(['fspra.fidelity.driver' => 'manual']);
        $u = $this->resident('1190', false);
        $this->actingAs($u)->postJson('/api/verify/start', ['national_id' => '63-123456-A-12', 'stand_number' => '1190', 'consent' => true])->assertOk()->assertJson(['status' => 'review']);
        $this->actingAs($u)->getJson('/api/requests')->assertForbidden();
    }

    public function test_admin_html_is_sanitised_on_public_pages(): void
    {
        Notice::create(['title' => 'Test', 'slug' => 'xss-test', 'category' => 'services', 'body' => '<p>Hi<script>alert(1)</script><a href="javascript:alert(1)">x</a></p>', 'published_at' => now()->subMinute()]);
        $html = $this->get('/notices/xss-test')->assertOk()->getContent();
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    public function test_voice_assistant_requires_sign_in(): void
    {
        $this->postJson('/api/assistant/live', ['consent' => true])->assertUnauthorized();
    }
}
