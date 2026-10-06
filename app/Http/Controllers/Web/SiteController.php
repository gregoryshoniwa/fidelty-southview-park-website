<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\CommitteeMember;
use App\Models\CommunityPage;
use App\Models\Faq;
use App\Models\Minute;
use App\Models\Notice;
use App\Models\Partner;
use App\Models\Resident;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Sponsorship;
use App\Models\Subscriber;
use App\Models\Thread;
use App\Services\OtpService;
use App\Services\Phone;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SiteController extends Controller
{
    public static function stats(): array
    {
        return Cache::remember('public-stats', 300, function () {
            $answered = Thread::whereNull('partner_id')->whereNotNull('first_response_at')->where('created_at', '>=', now()->subDays(90));
            $total = (clone $answered)->count();
            $fast = (clone $answered)->whereRaw('TIMESTAMPDIFF(HOUR, created_at, first_response_at) <= 48')->count();

            return [
                'verified' => Resident::where('verification_status', 'verified')->count(),
                'agreements' => ServiceRequest::whereHas('service', fn ($s) => $s->where('slug', 'my-agreement'))->count(),
                'deeds' => ServiceRequest::whereHas('service', fn ($s) => $s->where('slug', 'title-deed-tracker'))->count(),
                'answered_pct' => $total ? (int) round($fast / $total * 100) : null,
                'open_inbox' => Thread::whereNull('partner_id')->where('status', 'open')->count(),
            ];
        });
    }

    public function home()
    {
        return view('site.home', [
            'services' => Service::where('enabled', true)->with('partner')->orderBy('phase')->orderBy('sort')->take(6)->get(),
            'notices' => Notice::published()->orderByDesc('pinned')->orderByDesc('published_at')->take(4)->get(),
            'pages' => CommunityPage::where('active', true)->orderByDesc('verified')->take(3)->get(),
            'partners' => Partner::where('active', true)->whereNotNull('logo_path')->whereIn('type', ['developer', 'law_firm', 'bank'])->get(),
            'stats' => self::stats(),
            'hero' => Sponsorship::live('hero_takeover')->first(),
            'tile' => Sponsorship::live('sponsored_tile')->inRandomOrder()->first(),
            'rect' => Sponsorship::live('medium_rect')->inRandomOrder()->first(),
            'half' => Sponsorship::live('half_page')->inRandomOrder()->first(),
        ]);
    }

    public function services()
    {
        return view('site.services', ['services' => Service::where('enabled', true)->with('partner')->orderBy('phase')->orderBy('sort')->get()]);
    }

    public function service(Service $service)
    {
        abort_unless($service->enabled, 404);

        return view('site.service', ['service' => $service->load('partner'), 'faqs' => Faq::where('published', true)->where('topic', $service->slug)->orderBy('sort')->get()]);
    }

    public function notices(Request $request)
    {
        $cat = $request->query('category');
        $q = Notice::published()->orderByDesc('pinned')->orderByDesc('published_at');
        if ($cat && array_key_exists($cat, Notice::CATEGORIES)) {
            $q->where('category', $cat);
        }

        return view('site.notices', ['notices' => $q->paginate(12)->withQueryString(), 'category' => $cat, 'rect' => Sponsorship::live('medium_rect')->inRandomOrder()->first()]);
    }

    public function notice(Notice $notice)
    {
        abort_unless($notice->published_at && $notice->published_at->isPast(), 404);

        return view('site.notice', ['notice' => $notice, 'more' => Notice::published()->where('id', '!=', $notice->id)->latest('published_at')->take(3)->get()]);
    }

    public function community(Request $request)
    {
        $type = $request->query('type');
        $q = CommunityPage::where('active', true)->withCount('followers');
        if ($type && array_key_exists($type, CommunityPage::TYPES)) {
            $q->where('type', $type);
        }

        return view('site.community', ['pages' => $q->orderByDesc('verified')->orderBy('name')->paginate(18)->withQueryString(), 'type' => $type,
            'tiles' => Sponsorship::live('sponsored_tile')->where('advertiser', '!=', 'Southview Park Residents Association')->take(2)->get()]);
    }

    public function page(CommunityPage $page)
    {
        abort_unless($page->active, 404);

        return view('site.page', ['page' => $page->loadCount('followers'),
            'posts' => $page->posts()->whereNull('hidden_at')->latest('published_at')->take(10)->get(),
            'products' => $page->products()->where('active', true)->get()]);
    }

    public function about()
    {
        return view('site.about', [
            'page' => CmsPage::where('slug', 'about')->where('published', true)->first(),
            'members' => CommitteeMember::where('public', true)->orderBy('sort')->get(),
            'minutes' => Minute::whereNotNull('published_at')->latest('meeting_date')->take(12)->get(),
            'stats' => self::stats(),
        ]);
    }

    public function constitution()
    {
        return $this->cms('constitution');
    }

    public function minute(Minute $minute)
    {
        abort_unless($minute->published_at, 404);

        return view('site.minute', ['minute' => $minute]);
    }

    public function faq()
    {
        return view('site.faq', ['faqs' => Faq::where('published', true)->orderBy('topic')->orderBy('sort')->get()->groupBy('topic')]);
    }

    public function advertise()
    {
        return view('site.advertise', ['slots' => Sponsorship::SLOTS, 'page' => CmsPage::where('slug', 'advertise')->where('published', true)->first()]);
    }

    public function fees()
    {
        return view('site.fees', ['services' => Service::where('enabled', true)->with('partner')->orderBy('phase')->get()]);
    }

    public function cms(string $slug)
    {
        $page = CmsPage::where('slug', $slug)->where('published', true)->firstOrFail();

        return view('site.cms', ['page' => $page]);
    }

    public function subscribe(Request $request, OtpService $otp)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20'], 'categories' => ['nullable', 'array'], 'categories.*' => ['in:'.implode(',', array_keys(Notice::CATEGORIES))], 'website' => ['prohibited']]);
        $phone = Phone::normalise($data['phone']);
        if (! $phone) {
            return back()->withErrors(['phone' => 'Enter a valid mobile number.'])->withInput();
        }
        $sub = Subscriber::firstOrNew(['phone' => $phone]);
        $isNew = ! $sub->exists;
        $sub->categories = $data['categories'] ?? ['urgent', 'services', 'deeds', 'security'];
        $sub->save();
        // One confirmation SMS per number, ever: the form cannot be used to pump SMS.
        if ($isNew) {
            app(SmsService::class)->send($phone, 'You are subscribed to official Southview Park notices. Reply STOP to unsubscribe.', 'subscribe');
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Subscribed. Official notices will reach you by SMS.']);
    }

    public function sitemap()
    {
        $urls = collect([
            ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => url('/services'), 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => url('/notices'), 'priority' => '0.8', 'changefreq' => 'daily'],
            ['loc' => url('/community'), 'priority' => '0.7', 'changefreq' => 'weekly'],
            ['loc' => url('/about'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => url('/faq'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => url('/fees'), 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['loc' => url('/advertise'), 'priority' => '0.5', 'changefreq' => 'monthly'],
        ]);
        foreach (Service::where('enabled', true)->get() as $s) {
            $urls->push(['loc' => url('/services/'.$s->slug), 'lastmod' => $s->updated_at->toAtomString(), 'priority' => '0.8']);
        }
        foreach (Notice::published()->latest('published_at')->take(500)->get() as $n) {
            $urls->push(['loc' => url('/notices/'.$n->slug), 'lastmod' => $n->updated_at->toAtomString(), 'priority' => '0.6']);
        }
        foreach (CommunityPage::where('active', true)->get() as $p) {
            $urls->push(['loc' => url('/community/'.$p->slug), 'lastmod' => $p->updated_at->toAtomString(), 'priority' => '0.5']);
        }
        foreach (CmsPage::where('published', true)->whereIn('slug', ['constitution', 'privacy', 'terms', 'complaints'])->get() as $p) {
            $urls->push(['loc' => url('/'.$p->slug), 'lastmod' => $p->updated_at->toAtomString(), 'priority' => '0.3']);
        }

        return response()->view('site.sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots()
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Allow: /', 'Disallow: /app', 'Disallow: /partner', 'Disallow: /admin', 'Disallow: /api', 'Disallow: /go/', '', 'Sitemap: '.url('/sitemap.xml')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
    }

    public function manifest()
    {
        return response()->json([
            'name' => 'Southview Park Residents',
            'short_name' => 'Southview',
            'description' => 'Fidelity Southview Park Residents Association: verify your stand, track your deed, pay bills.',
            'start_url' => '/app?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#FAF7F0',
            'theme_color' => '#073320',
            'lang' => 'en-ZW',
            'icons' => [
                ['src' => '/images/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/images/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/images/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
