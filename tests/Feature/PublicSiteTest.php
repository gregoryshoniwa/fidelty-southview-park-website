<?php

namespace Tests\Feature;

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
            ['/community/tariro-primary-school'], ['/about'], ['/faq'], ['/advertise'], ['/fees'], ['/privacy'], ['/terms'],
            ['/complaints'], ['/constitution'], ['/app'], ['/app/login'], ['/partner/login']];
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

    public function test_home_has_seo_essentials(): void
    {
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('<link rel="canonical"', $html);
        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('"@type":"Organization"', $html);
        $this->assertStringContainsString('og:image', $html);
        $this->assertMatchesRegularExpression('/<h1[^>]*>/', $html);
        $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html), 'exactly one h1');
        $this->assertStringContainsString('Marufu Attorneys', $html);
    }

    public function test_pay_bills_shows_coming_soon(): void
    {
        $this->get('/services/pay-bills')->assertSee('Coming soon');
    }

    public function test_schools_are_listings_not_partners(): void
    {
        $this->get('/community/tariro-primary-school')->assertSee('not yet a partner');
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

    public function test_subscribe_form_validates_and_honeypot(): void
    {
        $this->post('/subscribe', ['phone' => 'abc'])->assertSessionHasErrors('phone');
        $this->post('/subscribe', ['phone' => '0771234567', 'website' => 'spam'])->assertSessionHasErrors('website');
        $this->post('/subscribe', ['phone' => '0771234567'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('subscribers', ['phone' => '+263771234567']);
    }

    public function test_ad_click_counts_and_redirects(): void
    {
        $ad = Sponsorship::first();
        $this->get('/go/ad/'.$ad->id)->assertRedirect();
        $this->assertSame(1, $ad->fresh()->clicks);
    }
}
