<?php

namespace Tests\Feature;

use App\Models\CommunityPage;
use App\Models\Faq;
use App\Models\Partner;
use App\Models\Poll;
use App\Models\Thread;
use App\Services\AssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
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
        CommunityPage::create(['type' => 'school', 'name' => 'Test School', 'slug' => 'tariro-primary-school', 'tagline' => 'A school', 'address' => 'Harare', 'verified' => false, 'active' => true]);
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

    public function test_assistant_falls_back_to_lighter_model_when_gemini_is_busy(): void
    {
        config(['fspra.gemini.api_key' => 'test-key', 'fspra.gemini.text_model' => 'main-model', 'fspra.gemini.text_fallback_model' => 'lite-model']);
        Http::fake([
            '*/models/main-model:*' => Http::sequence()->push(['error' => ['message' => 'high demand']], 503)->push(['candidates' => [['content' => ['parts' => []], 'finishReason' => 'OTHER']]])->whenEmpty(Http::response([], 503)),
            '*/models/lite-model:*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Five law firms handle deeds.']]]]]]),
        ]);
        $this->postJson('/api/assistant/chat', ['message' => 'Who handles deeds?'])->assertOk()->assertJsonPath('reply', 'Five law firms handle deeds.');
        $this->postJson('/api/assistant/chat', ['message' => 'Who handles deeds?'])->assertOk()->assertJsonPath('reply', 'Five law firms handle deeds.'); // empty reply: falls back too

        Http::fake(['*' => fn () => throw new ConnectionException('timed out')]);
        $this->postJson('/api/assistant/chat', ['message' => 'Who handles deeds?'])->assertOk()->assertJsonPath('escalate_suggested', true);
    }

    public function test_assistant_knows_the_whole_website_and_refreshes_after_changes(): void
    {
        $assistant = app(AssistantService::class);
        $knowledge = $assistant->knowledge();
        foreach (['Marufu Attorneys', 'Sinyoro & Partners', 'Title Deed Tracker', '/fees', 'national ID or passport'] as $fact) {
            $this->assertStringContainsString($fact, $knowledge);
        }

        Faq::create(['question' => 'Where is the clubhouse?', 'answer' => 'Next to the park on Jacaranda Street.', 'topic' => 'general', 'published' => true]);
        $this->assertStringContainsString('Jacaranda Street', $assistant->knowledge());
        $this->assertStringContainsString('Tariro', $assistant->systemPrompt(false));
    }

    public function test_web_search_is_limited_to_southview_park_and_current_partners(): void
    {
        $assistant = app(AssistantService::class);
        $this->assertTrue($assistant->inScope('Fidelity Southview Park water supply'));
        $this->assertTrue($assistant->inScope('Marufu office hours'));
        $this->assertTrue($assistant->inScope('Nyangulu Harare contact'));
        $this->assertFalse($assistant->inScope('latest football scores'));
        $this->assertFalse($assistant->inScope('Harare law firms'));
        Partner::where('name', 'Marufu Attorneys')->update(['active' => false]);
        $this->assertFalse($assistant->inScope('Marufu office hours'));

        Http::fake();
        $this->assertArrayHasKey('error', $assistant->searchWeb('best restaurants in Harare'));
        Http::assertNothingSent();
    }

    public function test_assistant_searches_the_web_when_the_model_asks(): void
    {
        config(['fspra.gemini.api_key' => 'test-key', 'fspra.gemini.text_model' => 'main-model', 'fspra.gemini.text_fallback_model' => null]);
        Http::fake(function ($request) {
            $body = $request->data();
            if (isset($body['tools'][0]['google_search'])) {
                return Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Marufu Attorneys opens at 8am.']]],
                    'groundingMetadata' => ['groundingChunks' => [['web' => ['uri' => 'https://example.test/marufu', 'title' => 'Marufu']]]]]]]);
            }
            $answered = collect($body['contents'])->contains(fn ($c) => isset($c['parts'][0]['functionResponse']));

            return Http::response(['candidates' => [['content' => ['role' => 'model', 'parts' => [$answered
                ? ['text' => 'From the web: Marufu Attorneys opens at 8am.']
                : ['functionCall' => ['name' => 'search_web', 'args' => ['query' => 'Marufu Attorneys opening hours']], 'thoughtSignature' => 'sig']]]]]]);
        });

        $r = $this->postJson('/api/assistant/chat', ['message' => 'When does Marufu open?'])->assertOk();
        $r->assertJsonPath('reply', 'From the web: Marufu Attorneys opens at 8am.')->assertJsonPath('sources.0.url', 'https://example.test/marufu');
        Http::assertSent(fn ($req) => collect($req->data()['contents'] ?? [])->contains(fn ($c) => ($c['parts'][0]['thoughtSignature'] ?? null) === 'sig'));
    }

    public function test_voice_search_endpoint_requires_sign_in_and_enforces_scope(): void
    {
        $this->postJson('/api/assistant/search', ['query' => 'Marufu'])->assertUnauthorized();
        $this->actingAs($this->resident('1146'))->postJson('/api/assistant/search', ['query' => 'football scores'])->assertOk()->assertJsonStructure(['error']);
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
