<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_resident_verifies_stand_with_fidelity_match(): void
    {
        $u = $this->resident('1100', false);
        $this->actingAs($u)->postJson('/api/verify/start', ['national_id' => '63-1234567-B-12', 'stand_number' => '1100', 'consent' => true])->assertOk();
        $code = cache('otp:last:'.$u->phone.':verify');
        $this->actingAs($u)->postJson('/api/verify/confirm', ['code' => $code])->assertOk()->assertJsonPath('user.resident.verification_status', 'verified');

        $r = $u->fresh()->resident;
        $this->assertSame('7B12', $r->national_id_last4);
        $this->assertNotSame('63-1234567-B-12', $r->national_id_hash, 'ID is never stored in clear');
        $this->assertSame(64, strlen($r->national_id_hash));
        $this->assertDatabaseHas('consents', ['user_id' => $u->id, 'purpose' => 'fidelity_verification']);
        $this->assertTrue(AuditLog::where('action', 'verification.completed')->exists());
    }

    public function test_consent_is_required(): void
    {
        $u = $this->resident('1101', false);
        $this->actingAs($u)->postJson('/api/verify/start', ['national_id' => '63-123456-A-12', 'stand_number' => '1101'])->assertStatus(422)->assertJsonValidationErrors('consent');
    }

    public function test_unknown_stand_does_not_match(): void
    {
        $u = $this->resident('1102', false);
        $this->actingAs($u)->postJson('/api/verify/start', ['national_id' => '63-123456-A-12', 'stand_number' => '99999', 'consent' => true])->assertStatus(422)->assertJsonValidationErrors('stand_number');
    }

    public function test_stand_cannot_be_verified_twice(): void
    {
        $this->resident('1103', true);
        $u = $this->resident('2000', false);
        $this->actingAs($u)->postJson('/api/verify/start', ['national_id' => '63-123456-A-12', 'stand_number' => '1103', 'consent' => true])->assertStatus(422);
    }
}
