@extends('layouts.site', ['canonical' => url('/')])

@push('head')
    <link rel="preload" as="image" href="/images/hero-estate-1280.webp" imagesrcset="/images/hero-estate-768.webp 768w, /images/hero-estate-1280.webp 1280w, /images/hero-estate-1920.webp 1920w" imagesizes="100vw" fetchpriority="high">
@endpush

@section('content')
{{-- HERO --}}
@php $heroVideo = \App\Models\Setting::get('hero_video_path'); @endphp
<section class="relative isolate overflow-hidden bg-forest-900 text-cream" aria-labelledby="hero-title">
    <picture>
        <source type="image/webp" srcset="/images/hero-estate-768.webp 768w, /images/hero-estate-1280.webp 1280w, /images/hero-estate-1920.webp 1920w" sizes="100vw">
        <img src="/images/hero-estate-1280.jpg" alt="" width="1280" height="720" fetchpriority="high" decoding="async" class="absolute inset-0 -z-20 size-full object-cover">
    </picture>
    @if ($heroVideo)
        <video class="absolute inset-0 -z-20 hidden size-full object-cover" data-hero-video data-src="{{ asset($heroVideo) }}" muted loop playsinline preload="none" poster="/images/hero-estate-1280.webp" aria-hidden="true"></video>
    @endif
    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-forest-950/95 via-forest-900/80 to-forest-900/30" aria-hidden="true"></div>

    <div class="wrap grid items-center gap-12 py-20 lg:grid-cols-[1.15fr_.85fr] lg:py-28">
        <div class="flex flex-col gap-6">
            <span class="chip animate-rise self-start border border-gold-500/40 bg-gold-500/15 text-gold-400">Amalinda, Harare</span>
            <h1 id="hero-title" class="animate-rise font-serif text-[40px] font-bold leading-[1.04] [animation-delay:.08s] sm:text-5xl lg:text-[60px]">Services first.<br>Trust earned,<br><span class="text-gold-500">not asked for.</span></h1>
            <p class="animate-rise max-w-xl text-lg leading-relaxed text-cream/85 [animation-delay:.16s]">Recover your Agreement of Sale, track your title deed, and talk to your committee privately. No levies. Every fee printed before you pay.</p>
            <div class="animate-rise flex flex-wrap items-center gap-3 [animation-delay:.24s]">
                <a href="/app/verify" class="btn btn-gold">Verify my stand <x-lucide name="arrow-right" class="size-4" /></a>
                <a href="#services" class="btn btn-ghost">See the services</a>
            </div>
            <ul class="animate-rise flex flex-wrap gap-x-5 gap-y-2 text-sm text-cream/75 [animation-delay:.3s]">
                <li class="flex items-center gap-1.5"><x-lucide name="check" class="size-4 text-gold-500" />Free to join</li>
                <li class="flex items-center gap-1.5"><x-lucide name="check" class="size-4 text-gold-500" />Backed by Fidelity Life, Marufu Attorneys and TN CyberTech Bank</li>
            </ul>
        </div>
        <div class="hidden flex-col items-end gap-3 lg:flex" aria-hidden="true">
            @foreach ([
                ['badge-check', 'bg-forest-100 text-forest-700', 'Verified resident', 'Your stand linked to your account in two minutes'],
                ['landmark', 'bg-gold-100 text-gold-600', 'Title deed: step 4 of 6', 'Updated by the lawyers, message them from your phone'],
                ['file-text', 'bg-forest-100 text-forest-700', 'Agreement of Sale downloaded', 'From Fidelity Life records, saved to your phone'],
            ] as $i => [$icon, $tone, $t, $s])
                <div class="animate-rise flex w-full max-w-md items-center gap-4 rounded-[12px] bg-white p-4 text-ink shadow-2xl" style="animation-delay: {{ .25 + $i * .1 }}s">
                    <span class="flex size-12 items-center justify-center rounded-[8px] {{ $tone }}"><x-lucide :name="$icon" class="size-6" /></span>
                    <span class="flex flex-col"><span class="font-extrabold text-forest-900">{{ $t }}</span><span class="text-[13px] text-muted">{{ $s }}</span></span>
                </div>
            @endforeach
        </div>
    </div>
    <div class="wrap pb-6">
        <p class="inline-flex items-center gap-2 rounded-[6px] border border-white/15 bg-forest-950/60 px-3 py-1.5 text-xs text-cream/80">
            <span class="sponsored-label !text-gold-500">Presented by</span>
            <span class="font-bold">{{ $hero?->advertiser ?? 'Fidelity Southview Park Residents Association' }}</span>
        </p>
    </div>
</section>

{{-- COUNTERS --}}
<section class="border-b border-line" aria-label="What we have done">
    <div class="wrap py-9">
        <p class="eyebrow mb-4 flex items-center gap-2 text-forest-700"><span class="size-2 rounded-full bg-forest-500 ring-4 ring-forest-500/20"></span>Counting since launch day</p>
        <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-5">
            @foreach ([[$stats['verified'], 'Residents verified'], [$stats['agreements'], 'Agreement of Sale requests'], [$stats['deeds'], 'Title deed files opened'], [$stats['answered_pct'] !== null ? $stats['answered_pct'].'%' : 'New', 'Questions answered within 48 hours']] as [$n, $label])
                <div class="card flex flex-col gap-1 px-5 py-4">
                    <dt class="order-2 text-sm font-semibold text-muted">{{ $label }}</dt>
                    <dd class="order-1 font-serif text-4xl font-bold text-forest-700" data-count="{{ is_numeric($n) ? $n : '' }}">{{ $n }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>

{{-- SERVICES --}}
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

{{-- HOW IT WORKS --}}
<section class="border-y border-line bg-white" aria-labelledby="how-title">
    <div class="wrap grid items-center gap-12 py-20 lg:grid-cols-2 lg:py-24">
        <div class="flex flex-col gap-5">
            <x-section-head eyebrow="How it works" title="Three steps. No meetings required." lead="We do not ask you to join and then wait. You use a service, you see the result, and you decide whether we have earned your membership." id="how-title" />
            <a href="/app/verify" class="btn btn-gold self-start">Start with Verify Me</a>
        </div>
        <ol class="divide-y divide-line">
            @foreach ([
                ['Verify your stand', 'Your ID number and stand number are matched against Fidelity Life records. A one-time code goes to the phone they have on file.'],
                ['Use a service', 'Recover your agreement, open your deed file, message the partner on your case. Any fee is printed before the button.'],
                ['Have your say', 'Verified residents vote in polls and write to the committee privately. After 12 months, you vote on the committee itself.'],
            ] as $i => [$t, $d])
                <li class="reveal flex gap-5 py-6">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-[8px] font-serif text-xl font-bold {{ $i === 2 ? 'bg-gold-500 text-forest-900' : 'bg-forest-700 text-gold-500' }}">{{ $i + 1 }}</span>
                    <span class="flex flex-col gap-1"><span class="text-lg font-extrabold text-forest-900">{{ $t }}</span><span class="text-[15px] leading-relaxed text-muted">{{ $d }}</span></span>
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
<section id="notices" class="border-y border-line bg-white" aria-labelledby="notices-title">
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
<section class="border-b border-line" aria-labelledby="partners-title">
    <div class="wrap flex flex-col items-center gap-6 py-12">
        <h2 id="partners-title" class="eyebrow font-sans text-muted">Partners who stand behind each service</h2>
        <ul class="flex flex-wrap items-center justify-center gap-4">
            @foreach ($partners as $p)
                <li class="flex h-20 min-w-44 items-center justify-center rounded-[8px] border border-line bg-white px-6">
                    <img src="{{ $p->logoUrl() }}" alt="{{ $p->name }}" loading="lazy" decoding="async" class="max-h-12 max-w-40 object-contain">
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
