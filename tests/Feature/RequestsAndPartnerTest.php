<?php

namespace Tests\Feature;

use App\Models\InAppNotification;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequestsAndPartnerTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function openDeed($u): string
    {
        return $this->actingAs($u)->postJson('/api/services/title-deed-tracker/requests', [])->assertCreated()->json('reference');
    }

    public function test_resident_opens_deed_file_and_uploads_document(): void
    {
        Storage::fake('local');
        $u = $this->resident('1110');
        $ref = $this->openDeed($u);
        $this->assertStringStartsWith('DEED-', $ref);
        $this->actingAs($u)->post("/api/requests/$ref/documents", ['kind' => 'agreement_of_sale', 'file' => UploadedFile::fake()->create('a.pdf', 200, 'application/pdf'), 'consent' => '1'], ['Accept' => 'application/json'])->assertCreated();
        $this->actingAs($u)->getJson("/api/requests/$ref")->assertOk()->assertJsonCount(1, 'data.documents')->assertJsonPath('data.steps.0', 'Documents received');
        // second open returns the existing file, never a duplicate
        $this->actingAs($u)->postJson('/api/services/title-deed-tracker/requests', [])->assertOk()->assertJson(['existing' => true, 'reference' => $ref]);
    }

    public function test_upload_rejects_dangerous_files(): void
    {
        Storage::fake('local');
        $u = $this->resident('1111');
        $ref = $this->openDeed($u);
        $this->actingAs($u)->post("/api/requests/$ref/documents", ['kind' => 'other', 'file' => UploadedFile::fake()->create('x.php', 10, 'application/x-php'), 'consent' => '1'], ['Accept' => 'application/json'])->assertStatus(422);
        $this->actingAs($u)->post("/api/requests/$ref/documents", ['kind' => 'other', 'file' => UploadedFile::fake()->create('big.pdf', 9000, 'application/pdf'), 'consent' => '1'], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_residents_cannot_see_each_others_requests_or_documents(): void
    {
        Storage::fake('local');
        $a = $this->resident('1112');
        $b = $this->resident('1113');
        $ref = $this->openDeed($a);
        $this->actingAs($a)->post("/api/requests/$ref/documents", ['kind' => 'national_id', 'file' => UploadedFile::fake()->image('id.jpg'), 'consent' => '1'], ['Accept' => 'application/json']);
        $doc = ServiceRequest::where('reference', $ref)->first()->documents()->first();
        $this->actingAs($b)->getJson("/api/requests/$ref")->assertNotFound();
        $this->actingAs($b)->get('/api/documents/'.$doc->ulid, ['Accept' => 'application/json'])->assertNotFound();
        $this->actingAs($a)->get('/api/documents/'.$doc->ulid)->assertOk();
    }

    public function test_partner_two_factor_login(): void
    {
        $staff = $this->partnerUser('marufu-attorneys');
        $this->postJson('/api/partner/auth/password', ['phone' => $staff->phone, 'password' => 'wrong-password'])->assertStatus(422);
        $this->postJson('/api/partner/auth/password', ['phone' => $staff->phone, 'password' => 'Secret-pass-123'])->assertOk()->assertJson(['otp_sent' => true]);
        $this->assertGuest();
        $code = cache('otp:last:'.$staff->phone.':partner_2fa');
        $this->postJson('/api/partner/auth/otp', ['code' => $code])->assertOk();
        $this->assertAuthenticatedAs($staff);
    }

    public function test_partner_sees_only_its_own_queue_and_updates_notify_resident(): void
    {
        $u = $this->resident('1114');
        $ref = $this->openDeed($u);
        $marufu = $this->partnerUser('marufu-attorneys');
        $bank = $this->partnerUser('tn-cybertech-bank');

        $this->actingAs($marufu)->getJson('/api/partner/requests')->assertOk()->assertJsonFragment(['reference' => $ref]);
        $this->actingAs($bank)->getJson('/api/partner/requests')->assertOk()->assertJsonMissing(['reference' => $ref]);
        $this->actingAs($bank)->getJson("/api/partner/requests/$ref")->assertNotFound();

        $this->actingAs($marufu)->patchJson("/api/partner/requests/$ref", ['step' => 2, 'note' => 'Balance confirmed'])->assertOk()->assertJsonPath('data.step', 2);
        $this->assertTrue(InAppNotification::where('user_id', $u->id)->where('title', 'like', '%updated%')->exists());
        $this->actingAs($marufu)->patchJson("/api/partner/requests/$ref", ['step' => 99])->assertStatus(422);
    }

    public function test_partner_and_resident_message_each_other(): void
    {
        $u = $this->resident('1115');
        $ref = $this->openDeed($u);
        $staff = $this->partnerUser('marufu-attorneys');
        $thread = $this->actingAs($staff)->postJson("/api/partner/requests/$ref/messages", ['body' => 'Please upload your ID.'])->assertCreated()->json('thread');
        $this->actingAs($u)->getJson('/api/threads/'.$thread)->assertOk()->assertJsonPath('data.messages.0.body', 'Please upload your ID.');
        $this->actingAs($u)->postJson("/api/threads/$thread/messages", ['body' => 'Done, <script>alert(1)</script>thanks'])->assertCreated();
        $this->actingAs($staff)->getJson('/api/partner/threads/'.$thread)->assertOk()->assertJsonPath('data.messages.1.body', 'Done, alert(1)thanks');
        $other = $this->resident('1116');
        $this->actingAs($other)->getJson('/api/threads/'.$thread)->assertNotFound();
    }

    public function test_disabled_module_is_forbidden(): void
    {
        $staff = $this->partnerUser('marufu-attorneys');
        $this->actingAs($staff)->getJson('/api/partner/settlements')->assertForbidden();
        $this->actingAs($this->resident('1117'))->getJson('/api/partner/requests')->assertUnauthorized();
    }

    public function test_export_neutralises_spreadsheet_formulas(): void
    {
        $u = $this->resident('1118');
        $u->update(['name' => '=HYPERLINK("http://evil")']);
        $this->openDeed($u);
        $staff = $this->partnerUser('marufu-attorneys');
        $csv = $this->actingAs($staff)->get('/api/partner/export')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_partner_broadcast_reaches_only_its_clients(): void
    {
        $a = $this->resident('1119');
        $this->openDeed($a);
        $b = $this->resident('1120');
        $staff = $this->partnerUser('marufu-attorneys');
        $this->actingAs($staff)->postJson('/api/partner/broadcasts', ['body' => 'Registry closed Friday.', 'audience' => 'open_requests'])->assertCreated();
        $this->assertTrue(InAppNotification::where('user_id', $a->id)->where('body', 'Registry closed Friday.')->exists());
        $this->assertFalse(InAppNotification::where('user_id', $b->id)->exists());
    }
}
