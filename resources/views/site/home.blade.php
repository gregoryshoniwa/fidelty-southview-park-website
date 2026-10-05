@extends('layouts.site', ['canonical' => url('/')])

@push('head')
    <link rel="preload" as="image" href="/images/hero-estate-1280.webp" imagesrcset="/images/hero-estate-768.webp 768w, /images/hero-estate-1280.webp 1280w, /images/hero-estate-1376.webp 1376w" imagesizes="100vw" fetchpriority="high">
@endpush

@section('content')
{{-- HERO: three photos, each with its own message --}}
@php
    $heroVideo = \App\Models\Setting::get('hero_video_path');
    $slides = [
        [
            'img' => 'hero-estate', 'fallback' => 'jpg', 'label' => 'Southview Park at sunrise',
            'eyebrow' => 'Fidelity Southview Park, Amalinda',
            'title' => 'Services first.<br>Trust earned,<br><span class="text-gold-500">not asked for.</span>',
            'lead' => 'Recover your Agreement of Sale, track your title deed and talk to your committee privately. No levies. Every fee printed before you pay.',
            'primary' => ['/app/verify', 'Verify my stand'], 'secondary' => ['#services', 'See the services'],
        ],
        [
            'img' => 'hero-deed', 'fallback' => 'jpg', 'label' => 'Keys and title deed',
            'eyebrow' => 'Title deeds with Marufu Attorneys',
            'title' => 'Your title deed,<br><span class="text-gold-500">step by step.</span>',
            'lead' => 'Open your deed file once, upload your Agreement of Sale and ID, and follow all six steps from your phone. Message the lawyers on your file directly.',
            'primary' => ['/app/deed', 'Open my deed file'], 'secondary' => ['/services/title-deed-tracker', 'How it works'],
        ],
        [
            'img' => 'hero-evening', 'fallback' => 'jpg', 'label' => 'Jacaranda street at dusk',
            'eyebrow' => 'One calm, official channel',
            'title' => 'A safer, calmer<br><span class="text-gold-500">Southview Park.</span>',
            'lead' => 'Official notices dated and signed, SMS alerts for what matters, and a private line to your committee. No group chats, no noise.',
            'primary' => ['#notices', 'Get notices by SMS'], 'secondary' => ['/app/inbox/new', 'Write to the committee'],
        ],
    ];
@endphp
<section class="relative isolate overflow-hidden bg-forest-950 text-cream" aria-labelledby="hero-title" aria-roledescription="carousel">
    <div class="absolute inset-0 -z-20" x-data="heroSlides" data-slides="{{ count($slides) }}" aria-hidden="true">
        @foreach ($slides as $i => $sl)
            <picture class="hero-slide absolute inset-0 transition-opacity duration-[1400ms] ease-in-out" x-bind:class="slide{{ $i }}">
                <source type="image/webp" srcset="/images/{{ $sl['img'] }}-768.webp 768w, /images/{{ $sl['img'] }}-1280.webp 1280w, /images/{{ $sl['img'] }}-1376.webp 1376w" sizes="100vw">
                <img src="/images/{{ $sl['img'] }}-1280.{{ $sl['fallback'] }}" alt="" width="1376" height="768" decoding="async" @if($i === 0) fetchpriority="high" @else loading="lazy" @endif class="hero-kenburns size-full object-cover object-[68%_center] lg:object-center">
            </picture>
        @endforeach
    </div>
    @if ($heroVideo)
        <video class="absolute inset-0 -z-20 hidden size-full object-cover" data-hero-video data-src="{{ asset($heroVideo) }}" muted loop playsinline preload="none" poster="/images/hero-estate-1280.webp" aria-hidden="true"></video>
    @endif
    {{-- Readability: strong tint on phones (text covers the photo), soft left-side fade on large screens (photos already fade to green). --}}
    <div class="absolute inset-0 -z-10 bg-gradient-to-b from-forest-950/80 via-forest-950/65 to-forest-950/85 lg:bg-gradient-to-r lg:from-forest-950/75 lg:via-forest-950/25 lg:to-transparent" aria-hidden="true"></div>

    <div class="wrap flex min-h-[600px] flex-col justify-center py-16 lg:min-h-[680px] lg:py-24" x-on:mouseenter="heroPause" x-on:mouseleave="heroResume">
        <div class="grid max-w-2xl" x-data="heroText">
            @foreach ($slides as $i => $sl)
                <div class="col-start-1 row-start-1 flex flex-col gap-6 transition duration-700 ease-out" x-bind:class="text{{ $i }}" x-bind:aria-hidden="hidden{{ $i }}" @if($i > 0) aria-hidden="true" x-cloak @endif>
                    <p class="eyebrow text-gold-400">{{ $sl['eyebrow'] }}</p>
                    @if ($i === 0)
                        <h1 id="hero-title" class="font-serif text-[40px] font-bold leading-[1.04] sm:text-5xl lg:text-[60px]">{!! $sl['title'] !!}</h1>
                    @else
                        <p class="font-serif text-[40px] font-bold leading-[1.04] sm:text-5xl lg:text-[60px]">{!! $sl['title'] !!}</p>
                    @endif
                    <p class="max-w-xl text-lg leading-relaxed text-cream/90">{{ $sl['lead'] }}</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ $sl['primary'][0] }}" class="btn btn-gold" @if($i > 0) tabindex="-1" @endif x-bind:tabindex="tab{{ $i }}">{{ $sl['primary'][1] }} <x-lucide name="arrow-right" class="size-4" /></a>
                        <a href="{{ $sl['secondary'][0] }}" class="btn btn-ghost" @if($i > 0) tabindex="-1" @endif x-bind:tabindex="tab{{ $i }}">{{ $sl['secondary'][1] }}</a>
                    </div>
                </div>
            @endforeach
        </div>
        <ul class="mt-8 flex flex-wrap gap-x-5 gap-y-2 text-sm text-cream/80">
            <li class="flex items-center gap-1.5"><x-lucide name="check" class="size-4 text-gold-500" />Free to join</li>
            <li class="flex items-center gap-1.5"><x-lucide name="check" class="size-4 text-gold-500" />Backed by Fidelity Life, Marufu Attorneys and TN CyberTech Bank</li>
        </ul>
        <div class="mt-6 flex items-center gap-2" x-data="heroDots" role="group" aria-label="Choose a slide">
            @foreach ($slides as $i => $sl)
                <button type="button" class="h-1.5 rounded-full transition-all duration-500" x-bind:class="dot{{ $i }}" x-on:click="go{{ $i }}" aria-label="Show slide {{ $i + 1 }}: {{ $sl['label'] }}"></button>
            @endforeach
        </div>
    </div>
</section>

{{-- COUNTERS --}}
<section class="bg-forest-950 text-cream" aria-label="What we have done">
    <div class="wrap py-10">
        <p class="eyebrow mb-4 flex items-center gap-2 text-gold-400"><span class="size-2 rounded-full bg-gold-500 ring-4 ring-gold-500/25"></span>Counting since launch day</p>
        <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-5">
            @foreach ([[$stats['verified'], 'Residents verified'], [$stats['agreements'], 'Agreement of Sale requests'], [$stats['deeds'], 'Title deed files opened'], [$stats['answered_pct'] !== null ? $stats['answered_pct'].'%' : 'New', 'Questions answered within 48 hours']] as [$n, $label])
                <div class="flex flex-col gap-1 rounded-[12px] border border-white/10 bg-white/[0.06] px-5 py-4">
                    <dt class="order-2 text-sm font-semibold text-cream/75">{{ $label }}</dt>
                    <dd class="order-1 font-serif text-4xl font-bold text-gold-400" data-count="{{ is_numeric($n) ? $n : '' }}">{{ $n }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>

{{-- SERVICES --}}
<div class="bg-mint">
<section id="services" class="wrap py-20 lg:py-24" aria-labelledby="services-title">
    <div class="mb-10 flex flex-wrap items-end justify-between gap-6">
        <x-section-head eyebrow="What you can do" title="Real services, backed by named partners" lead="Every service has a partner standing behind it in writing, and its fee printed before you pay." id="services-title" />
        <a href="{{ route('services') }}" class="btn btn-outline">All services</a>
    </div>
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($services as $s)
            <div class="reveal"><x-service-card :service="$s" /></div>
        @endforeach
    </div>
</section>

{{-- BILLBOARD --}}
<div class="wrap pb-20"><x-ad placement="billboard" :ad="$billboard" class="mx-auto max-w-[970px]" /></div>
</div>

{{-- HOW IT WORKS --}}
<section class="bg-forest-800 text-cream" aria-labelledby="how-title">
    <div class="wrap grid items-center gap-12 py-20 lg:grid-cols-2 lg:py-24">
        <div class="flex flex-col gap-5">
            <x-section-head eyebrow="How it works" title="Three steps. No meetings required." lead="We do not ask you to join and then wait. You use a service, you see the result, and you decide whether we have earned your membership." dark id="how-title" />
            <a href="/app/verify" class="btn btn-gold self-start">Start with Verify Me</a>
        </div>
        <ol class="divide-y divide-white/10">
            @foreach ([
                ['Verify your stand', 'Your ID number and stand number are matched against Fidelity Life records. A one-time code goes to the phone they have on file.'],
                ['Use a service', 'Recover your agreement, open your deed file, message the partner on your case. Any fee is printed before the button.'],
                ['Have your say', 'Verified residents vote in polls and write to the committee privately. After 12 months, you vote on the committee itself.'],
            ] as $i => [$t, $d])
                <li class="reveal flex gap-5 py-6">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-[8px] font-serif text-xl font-bold {{ $i === 2 ? 'bg-gold-500 text-forest-900' : 'bg-white/10 text-gold-400' }}">{{ $i + 1 }}</span>
                    <span class="flex flex-col gap-1"><span class="text-lg font-extrabold text-cream">{{ $t }}</span><span class="text-[15px] leading-relaxed text-cream/75">{{ $d }}</span></span>
                </li>
            @endforeach
        </ol>
    </div>
</section>

{{-- COMMUNITY --}}
<section class="wrap py-20 lg:py-24" aria-labelledby="community-title">
    <div class="mb-10 flex flex-wrap items-end justify-between gap-6">
        <x-section-head eyebrow="Community" title="Businesses, churches and schools nearby" lead="Follow a page, buy from a neighbour, find a school. Local businesses can sponsor a tile here." id="community-title" />
        <a href="{{ route('community') }}" class="btn btn-outline">Open the directory</a>
    </div>
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($pages as $p)
            <a href="{{ route('community.page', $p) }}" class="card card-hover reveal flex flex-col overflow-hidden">
                <img src="{{ $p->cover_path ? asset($p->cover_path) : '/images/covers/'.$p->type.'.webp' }}" alt="" width="400" height="240" loading="lazy" decoding="async" class="aspect-[5/3] w-full object-cover">
                <span class="flex flex-col gap-1 p-4">
                    <span class="sponsored-label">{{ \App\Models\CommunityPage::TYPES[$p->type] ?? $p->type }}{{ $p->verified ? '' : ' · listing' }}</span>
                    <span class="font-extrabold text-forest-900">{{ $p->name }}</span>
                    <span class="text-[13px] text-muted">{{ $p->tagline }}</span>
                </span>
            </a>
        @endforeach
        <a href="{{ route('advertise') }}" class="reveal flex flex-col items-center justify-center gap-3 rounded-[12px] bg-forest-900 p-6 text-center text-cream">
            <span class="font-serif text-2xl font-bold text-gold-500">Advertise here</span>
            <span class="text-sm leading-relaxed text-cream/80">Sponsored tiles, banners and notices that reach every verified household.</span>
            <span class="btn btn-gold btn-sm mt-1">See rates</span>
        </a>
    </div>
</section>

{{-- NOTICES --}}
<section id="notices" class="bg-mint" aria-labelledby="notices-title">
    <div class="wrap py-20 lg:py-24">
        <div class="mb-10 flex flex-wrap items-end justify-between gap-6">
            <x-section-head eyebrow="Notice board" title="One official channel. Calm, dated, signed." lead="No group chats. Official notices only, each dated and signed by a committee role. Something to raise? Write to the committee privately." id="notices-title" />
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn btn-green" x-on:click="openSubscribe">Get notices by SMS</button>
                <a href="/app/inbox/new" class="btn btn-outline">Write to the committee</a>
            </div>
        </div>
        <div class="grid gap-8 lg:grid-cols-[1fr_300px]">
            <div class="card divide-y divide-line px-5 sm:px-6">
                @forelse ($notices as $n)
                    @include('partials.notice-row', ['n' => $n])
                @empty
                    <p class="py-8 text-muted">No notices yet.</p>
                @endforelse
                <div class="py-4"><a href="{{ route('notices') }}" class="font-bold text-forest-700 hover:underline">All notices <span aria-hidden="true">&rarr;</span></a></div>
            </div>
            <div class="hidden flex-col gap-6 lg:flex">
                <x-ad placement="medium_rect" :ad="$rect" />
                <x-ad placement="half_page" :ad="$half" />
            </div>
        </div>
    </div>
</section>

{{-- GOVERNANCE --}}
<section class="bg-forest-900 text-cream" aria-labelledby="gov-title">
    <div class="wrap grid items-center gap-12 py-20 lg:grid-cols-2 lg:py-24">
        <div class="flex flex-col gap-5">
            <x-section-head eyebrow="About the association" title="Run by rules you can read." lead="The constitution, the minutes of every meeting and the committee's term are public. Payments out need two signatories. The accounts are reviewed independently every year." dark id="gov-title" />
            <ul class="flex flex-col gap-2.5 text-[15px]">
                <li class="flex items-center gap-2.5"><x-lucide name="check" class="size-4 text-gold-500" />Fees printed before every payment</li>
                <li class="flex items-center gap-2.5"><x-lucide name="check" class="size-4 text-gold-500" />Minutes published within 7 days</li>
                <li class="flex items-center gap-2.5"><x-lucide name="check" class="size-4 text-gold-500" />Founding committee term ends after 12 months, then you vote</li>
            </ul>
            <div class="flex flex-wrap gap-3"><a href="{{ route('constitution') }}" class="btn btn-gold">Read the constitution</a><a href="{{ route('about') }}#minutes" class="btn btn-ghost">Latest minutes</a></div>
        </div>
        <dl class="grid grid-cols-2 gap-4">
            @foreach ([['7', 'Committee members, named with their roles'], ['2', 'Signatories on every payment out'], [$stats['open_inbox'], 'Open requests in the committee inbox'], [$stats['answered_pct'] !== null ? $stats['answered_pct'].'%' : 'New', 'Answered within 48 hours']] as [$n, $l])
                <div class="rounded-[12px] border border-white/15 bg-white/5 p-5"><dd class="font-serif text-4xl font-bold text-gold-500">{{ $n }}</dd><dt class="mt-1 text-sm text-cream/80">{{ $l }}</dt></div>
            @endforeach
        </dl>
    </div>
</section>

{{-- PARTNERS --}}
<section class="border-t border-wheat bg-wheat" aria-labelledby="partners-title">
    <div class="wrap flex flex-col items-center gap-6 py-12">
        <h2 id="partners-title" class="eyebrow font-sans text-muted">Partners who stand behind each service</h2>
        <ul class="flex flex-wrap items-center justify-center gap-4">
            @php
                // Gold logos sit on deep green; Fidelity Life (red on white) stays on white, in the middle.
                $order = ['marufu-attorneys' => 0, 'fidelity-life' => 1, 'tn-cybertech-bank' => 2];
                $dark = ['marufu-attorneys' => 'images/partners/marufu-attorneys-white.webp', 'tn-cybertech-bank' => null];
            @endphp
            @foreach ($partners->sortBy(fn ($p) => $order[$p->slug] ?? 9) as $p)
                @php $isDark = array_key_exists($p->slug, $dark); @endphp
                <li @class(['flex h-24 w-72 max-w-full items-center justify-center rounded-[12px] px-8', 'bg-forest-900 ring-1 ring-gold-500/30' => $isDark, 'border border-line bg-white' => ! $isDark])>
                    <img src="{{ $isDark && $dark[$p->slug] ? asset($dark[$p->slug]) : $p->logoUrl() }}" alt="{{ $p->name }}" loading="lazy" decoding="async" class="max-h-14 max-w-48 object-contain">
                </li>
            @endforeach
        </ul>
    </div>
</section>

{{-- CTA --}}
<section class="bg-gold-500 text-forest-900">
    <div class="wrap flex flex-col items-start justify-between gap-6 py-14 lg:flex-row lg:items-center">
        <div class="max-w-2xl">
            <h2 class="font-serif text-3xl font-bold sm:text-4xl">Stronger together. A better community.</h2>
            <p class="mt-2 text-[17px] text-forest-900/85">Verify your stand in two minutes. No fee, no levy, no obligation. Membership is yours to give, and ours to earn.</p>
        </div>
        <a href="/app/verify" class="btn bg-forest-900 text-cream hover:bg-forest-800">Verify my stand now</a>
    </div>
</section>

@include('partials.subscribe-dialog')
@endsection
