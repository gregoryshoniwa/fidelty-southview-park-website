<?php

namespace Tests\Feature;

use App\Models\CmsPage;
use App\Models\CommunityPage;
use App\Models\Notice;
use App\Models\Sponsorship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public static function pages(): array
    {
        return [['/'], ['/services'], ['/services/title-deed-tracker'], ['/services/pay-bills'], ['/notices'], ['/community'],
            ['/about'], ['/faq'], ['/advertise'], ['/fees'], ['/privacy'], ['/terms'],
            ['/complaints'], ['/app'], ['/app/login'], ['/partner/login']];
    }

    #[DataProvider('pages')]
    public function test_page_renders_with_security_headers(string $url): void
    {
        $res = $this->get($url)->assertOk();
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString("frame-ancestors 'none'", $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("object-src 'none'", $res->headers->get('Content-Security-Policy'));
        $this->assertNull($res->headers->get('X-Powered-By'));
    }

    public function test_notice_tabs_only_show_categories_with_published_notices(): void
    {
        $html = $this->get('/notices')->assertOk()->getContent();
        $this->assertStringContainsString('category=services', $html);
        $this->assertStringNotContainsString('category=events', $html);

        Notice::create(['title' => 'Fun day', 'slug' => 'fun-day', 'category' => 'events', 'body' => '<p>Saturday.</p>', 'published_at' => now()->subMinute()]);
        Notice::create(['title' => 'Later', 'slug' => 'later', 'category' => 'finance', 'body' => '<p>Soon.</p>', 'published_at' => now()->addDay()]);

        $html = $this->get('/notices')->getContent();
        $this->assertStringContainsString('category=events', $html);
        $this->assertStringNotContainsString('category=finance', $html);
        $this->actingAs($this->resident())->getJson('/api/notices')->assertOk()->assertJsonPath('categories.events', 'Events')->assertJsonMissingPath('categories.finance');
    }

    public function test_constitution_is_hidden_until_published(): void
    {
        $this->get('/constitution')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('Read the constitution')->assertDontSee('>Constitution<', false);
        $this->get('/about')->assertOk()->assertDontSee('Read the constitution');
        $this->assertStringNotContainsString('/constitution', $this->get('/sitemap.xml')->getContent());

        CmsPage::where('slug', 'constitution')->update(['published' => true]);

        $this->get('/constitution')->assertOk();
        $this->get('/')->assertSee('>Constitution<', false);
        $this->get('/about')->assertSee('Read the constitution');
    }

    public function test_home_has_seo_essentials(): void
    {
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('<link rel="canonical"', $html);
        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('"@type":"Organization"', $html);
        $this->assertStringContainsString('og:image', $html);
        $this->assertMatchesRegularExpression('/<h1[^>]*>/', $html);
        $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html), 'exactly one h1');
        $this->assertStringContainsString('Fidelity Life', $html);
    }

    public function test_pay_bills_shows_coming_soon(): void
    {
        $this->get('/services/pay-bills')->assertSee('Coming soon');
    }

    public function test_community_listing_page_says_it_is_not_a_partner(): void
    {
        CommunityPage::create(['type' => 'school', 'name' => 'Test School', 'slug' => 'test-school', 'tagline' => 'A school', 'address' => 'Harare', 'verified' => false, 'active' => true]);
        $this->get('/community/test-school')->assertOk()->assertSee('not yet a partner');
    }

    public function test_community_shows_an_invitation_card_for_each_section(): void
    {
        $this->get('/community')->assertOk()->assertSee('Your business here')->assertSee('Your church here')->assertSee('Your school here')->assertSee('Schools')->assertDontSee('Schooles');
        $this->get('/community?type=church')->assertSee('Your church here')->assertDontSee('Your school here');
    }

    public function test_sitemap_and_robots(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertSee('/services/title-deed-tracker', false);
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
        $this->get('/manifest.webmanifest')->assertOk()->assertJsonPath('short_name', 'Southview');
    }

    public function test_private_areas_are_noindex(): void
    {
        $this->get('/app')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/partner/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_unknown_page_is_branded_404(): void
    {
        $this->get('/does-not-exist')->assertNotFound()->assertSee('Page not found');
    }

    public function test_notices_are_followed_on_whatsapp_channel_when_configured(): void
    {
        config(['fspra.whatsapp.channel_url' => null]);
        $this->get('/notices')->assertOk()->assertDontSee('Follow the channel')->assertDontSee('by SMS');
        $this->get('/')->assertOk()->assertDontSee('by SMS')->assertDontSee('on WhatsApp');
        $this->post('/subscribe', ['phone' => '0771234567'])->assertNotFound();

        config(['fspra.whatsapp.channel_url' => 'https://whatsapp.com/channel/0029TestChannel']);

        $this->get('/notices')->assertSee('Follow the channel')->assertSee('https://whatsapp.com/channel/0029TestChannel');
        $this->get('/')->assertSee('Get notices on WhatsApp')->assertSee('Follow on WhatsApp');
    }

    public function test_ad_click_counts_and_redirects(): void
    {
        $ad = Sponsorship::first();
        $this->get('/go/ad/'.$ad->id)->assertRedirect();
        $this->assertSame(1, $ad->fresh()->clicks);
    }
}
