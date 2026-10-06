<?php

namespace Tests\Feature;

use App\Filament\Resources\Residents\ResidentResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneVerifyTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        config(['fspra.phone_provider' => 'none', 'fspra.whatsapp' => [
            'number' => '263771000000', 'verify_token' => 'verify-me', 'app_secret' => 'app-secret', 'token' => null, 'phone_number_id' => null,
        ]]);
    }

    private function whatsapp(string $from, string $text, ?string $secret = 'app-secret')
    {
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['value' => [
            'messages' => [['from' => $from, 'id' => 'wamid.1', 'type' => 'text', 'text' => ['body' => $text]]],
        ]]]]]]);

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, (string) $secret),
        ], $body);
    }

    private function newcomer(): User
    {
        $u = User::create(['name' => 'Rudo', 'email' => 'rudo@example.com', 'email_verified_at' => now()]);
        $u->assignRole('resident');
        $u->resident()->create([]);

        return $u;
    }

    public function test_config_reports_whatsapp_and_no_sms(): void
    {
        $this->getJson('/api/auth/config')->assertJson(['phone_provider' => 'none', 'whatsapp' => true]);
        config(['fspra.whatsapp.app_secret' => null]);
        $this->getJson('/api/auth/config')->assertJson(['whatsapp' => false]);
    }

    public function test_meta_webhook_handshake(): void
    {
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=12345')->assertOk()->assertSee('12345');
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=12345')->assertForbidden();
    }

    public function test_unsigned_webhook_is_refused(): void
    {
        $this->whatsapp('263772996330', 'VERIFY 123456', 'wrong-secret')->assertForbidden();
    }

    public function test_resident_links_number_by_sending_whatsapp(): void
    {
        $u = $this->newcomer();
        $this->actingAs($u);
        $c = $this->postJson('/api/me/phone/whatsapp')->assertOk()->json();
        $this->assertStringStartsWith('https://wa.me/263771000000?text=VERIFY%20', $c['link']);

        $this->getJson('/api/phone/whatsapp/'.$c['id'])->assertJson(['status' => 'pending']);
        $this->whatsapp('263772996330', 'VERIFY '.$c['code'])->assertOk();
        $this->getJson('/api/phone/whatsapp/'.$c['id'])->assertJson(['status' => 'done', 'user' => ['has_phone' => true]]);

        $u->refresh();
        $this->assertSame('+263772996330', $u->phone);
        $this->assertNotNull($u->phone_verified_at);
    }

    public function test_wrong_code_does_nothing_and_others_cannot_poll(): void
    {
        $this->actingAs($this->newcomer());
        $c = $this->postJson('/api/me/phone/whatsapp')->json();
        $this->whatsapp('263772996330', 'VERIFY 000000'.($c['code'] === '000000' ? '1' : ''))->assertOk();
        $this->getJson('/api/phone/whatsapp/'.$c['id'])->assertJson(['status' => 'pending']);

        $this->flushSession();
        $this->getJson('/api/phone/whatsapp/'.$c['id'])->assertNotFound();
    }

    public function test_new_resident_signs_up_with_whatsapp(): void
    {
        $c = $this->postJson('/api/auth/whatsapp')->assertOk()->json();
        $this->whatsapp('263772996330', 'verify '.$c['code']);
        $this->getJson('/api/phone/whatsapp/'.$c['id'])->assertJson(['status' => 'needs_details']);
        $this->assertGuest();

        $this->postJson('/api/auth/whatsapp/complete', ['name' => 'Tendai Moyo', 'accept_terms' => true])->assertOk()->assertJsonPath('user.has_phone', true);
        $u = User::where('phone', '+263772996330')->firstOrFail();
        $this->assertTrue($u->hasRole('resident'));
        $this->assertAuthenticatedAs($u);
    }

    public function test_existing_resident_signs_in_with_whatsapp(): void
    {
        $u = $this->resident('2001');
        $c = $this->postJson('/api/auth/whatsapp')->json();
        $this->whatsapp(ltrim($u->phone, '+'), 'VERIFY '.$c['code']);
        $this->getJson('/api/phone/whatsapp/'.$c['id'])->assertJson(['status' => 'done']);
        $this->assertAuthenticatedAs($u);
    }

    public function test_partner_accounts_cannot_sign_in_with_whatsapp(): void
    {
        $staff = $this->partnerUser('marufu-attorneys');
        $c = $this->postJson('/api/auth/whatsapp')->json();
        $this->whatsapp(ltrim($staff->phone, '+'), 'VERIFY '.$c['code']);
        $this->getJson('/api/phone/whatsapp/'.$c['id'])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_unconfirmed_number_waits_for_committee_and_is_never_used_for_sign_in(): void
    {
        $u = $this->newcomer();
        $this->actingAs($u);
        $this->postJson('/api/me/phone/unconfirmed', ['phone' => '0772996330'])->assertOk()
            ->assertJsonPath('user.has_phone', false)->assertJsonPath('user.phone_pending_masked', '+263 •• ••• 330');
        $this->assertNull($u->fresh()->phone);

        $this->assertTrue(ResidentResource::confirmPhoneFor($u->fresh()));
        $u->refresh();
        $this->assertSame('+263772996330', $u->phone);
        $this->assertNull($u->unconfirmed_phone);
    }

    public function test_whatsapp_proof_wins_over_someone_elses_unconfirmed_claim(): void
    {
        $squatter = $this->newcomer();
        $squatter->forceFill(['unconfirmed_phone' => '+263772996330'])->save();

        $owner = User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'email_verified_at' => now()]);
        $owner->assignRole('resident');
        $this->actingAs($owner);
        $c = $this->postJson('/api/me/phone/whatsapp')->json();
        $this->whatsapp('263772996330', 'VERIFY '.$c['code']);
        $this->getJson('/api/phone/whatsapp/'.$c['id'])->assertJson(['status' => 'done']);

        $this->assertSame('+263772996330', $owner->fresh()->phone);
        $this->assertNull($squatter->fresh()->unconfirmed_phone);
    }

    public function test_unconfirmed_entry_is_off_when_sms_is_available(): void
    {
        config(['fspra.phone_provider' => 'local']);
        $this->actingAs($this->newcomer());
        $this->postJson('/api/me/phone/unconfirmed', ['phone' => '0772996330'])->assertNotFound();
    }

    public function test_unconfirmed_number_is_enough_to_start_stand_verification(): void
    {
        $u = $this->newcomer();
        $u->forceFill(['unconfirmed_phone' => '+263772996330'])->save();
        $this->actingAs($u);
        $this->postJson('/api/verify/start', ['national_id' => '63-1198624K45', 'stand_number' => '5813', 'consent' => true])
            ->assertJsonMissingValidationErrors('phone');
    }
}
