<?php

namespace Tests\Feature;

use App\Models\Poll;
use App\Models\Thread;
use App\Services\AssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityAndAccountTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_resident_writes_to_committee_privately(): void
    {
        $u = $this->resident('1140');
        $ref = $this->actingAs($u)->postJson('/api/threads', ['subject' => 'Water outage', 'category' => 'water', 'body' => 'No water since Monday on our street.'])->assertCreated()->json('data.reference');
        $this->assertStringStartsWith('INB-', $ref);
        $this->assertNull(Thread::where('reference', $ref)->value('partner_id'));
    }

    public function test_one_vote_per_stand(): void
    {
        $u = $this->resident('1141');
        $poll = Poll::first();
        $this->actingAs($u)->postJson("/api/polls/{$poll->id}/vote", ['option' => 1])->assertCreated();
        $this->actingAs($u)->postJson("/api/polls/{$poll->id}/vote", ['option' => 2])->assertStatus(422);
        $this->actingAs($u)->postJson("/api/polls/{$poll->id}/vote", ['option' => 99])->assertStatus(422);
    }

    public function test_follow_community_page(): void
    {
        $u = $this->resident('1142');
        $this->actingAs($u)->postJson('/api/pages/tariro-primary-school/follow')->assertOk()->assertJson(['following' => true]);
        $this->actingAs($u)->getJson('/api/pages/tariro-primary-school')->assertJsonPath('data.following', true);
    }

    public function test_data_export_and_deletion_request(): void
    {
        $u = $this->resident('1143');
        $this->actingAs($u)->get('/api/me/export')->assertOk()->assertJsonStructure(['profile', 'consents', 'requests', 'payments']);
        $this->actingAs($u)->postJson('/api/me/delete-request', ['reason' => 'Moving'])->assertCreated();
    }

    public function test_profile_update_cannot_escalate_privileges(): void
    {
        $u = $this->resident('1144');
        $this->actingAs($u)->patchJson('/api/me', ['name' => 'New Name', 'status' => 'active', 'roles' => ['super_admin']])->assertOk();
        $this->assertFalse($u->fresh()->hasRole('super_admin'));
        $this->assertSame('New Name', $u->fresh()->name);
    }

    public function test_assistant_answers_from_help_pages_without_api_key_and_escalates(): void
    {
        app(AssistantService::class)->rebuildKnowledge();
        $r = $this->postJson('/api/assistant/chat', ['message' => 'Who is processing the title deeds?'])->assertOk();
        $this->assertStringContainsString('Marufu', $r->json('reply'));
        $u = $this->resident('1145');
        $this->actingAs($u)->postJson('/api/assistant/escalate', ['session_id' => $r->json('session_id'), 'summary' => 'Need help with my deed'])->assertCreated();
    }

    public function test_incident_report(): void
    {
        $u = $this->resident('1146');
        $this->actingAs($u)->postJson('/api/incidents', ['category' => 'suspicious', 'description' => 'Unknown car parked overnight.'])->assertCreated()->assertJsonStructure(['reference']);
    }

    public function test_admin_panel_rejects_residents(): void
    {
        $u = $this->resident('1147');
        $this->actingAs($u)->get('/admin')->assertForbidden();
    }
}
