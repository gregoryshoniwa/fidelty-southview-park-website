@php
    $comingSoon = ($service->slug === 'pay-bills' && !config('fspra.payments_live')) || $service->phase > 1;
    $cta = match ($service->slug) {
        'verify-me' => ['/app/verify', 'Verify my stand'],
        'my-agreement' => ['/app/agreement', 'Open My Agreement'],
        'title-deed-tracker' => ['/app/deed', 'Open my deed file'],
        'pay-bills' => ['/app/pay', 'Go to Pay Bills'],
        'notice-board' => [route('notices'), 'Read the notices'],
        'admin-inbox' => ['/app/inbox/new', 'Write to the committee'],
        'polls' => ['/app/polls', 'See polls'],
        'community-directory' => [route('community'), 'Open the directory'],
        'safety-and-security' => ['/app/security', 'Open Security'],
        default => ['/app/services/'.$service->slug, 'Open in the app'],
    };
@endphp
@extends('layouts.site', ['title' => $service->name, 'description' => $service->summary, 'breadcrumbs' => [['Home', url('/')], ['Services', route('services')], [$service->name, route('service', $service)]]])
@push('jsonld')
<script type="application/ld+json" nonce="{{ app('csp-nonce') }}">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'Service', 'name' => $service->name, 'description' => $service->summary, 'provider' => ['@id' => url('/#org')], 'areaServed' => 'Fidelity Southview Park, Harare', 'offers' => ['@type' => 'Offer', 'price' => $service->fee_type === 'flat' ? (string) $service->fee_amount : '0', 'priceCurrency' => $service->fee_currency]], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
@section('content')
@include('partials.page-hero', ['eyebrow' => $service->providerName(), 'heading' => $service->name, 'lead' => $service->summary, 'crumbs' => [['Services', route('services')], [$service->name, null]]])
<div class="wrap grid gap-10 py-14 lg:grid-cols-[1fr_340px]">
    <article class="prose-site max-w-3xl">
        @if ($comingSoon)
            <div class="not-prose mb-8 flex gap-4 rounded-[12px] border border-gold-500/40 bg-gold-100 p-5">
                <x-lucide name="clock" class="size-6 text-gold-600" />
                <div><p class="font-extrabold text-forest-900">Coming soon</p><p class="text-[15px] text-muted">{{ $service->slug === 'pay-bills' ? 'Online bill payments open once the TN CyberTech Bank gateway is connected. Subscribe to notices and we will tell you the day it opens.' : 'This service opens in phase '.$service->phase.'. We open each phase only after the partner has signed and the previous phase works.' }}</p></div>
            </div>
        @endif
        {!! \Illuminate\Support\Str::sanitizeHtml((string) $service->body) !!}
        @if ($faqs->isNotEmpty())
            <h2>Questions</h2>
            @foreach ($faqs as $f)<h3>{{ $f->question }}</h3><p>{{ $f->answer }}</p>@endforeach
        @endif
    </article>
    <aside class="flex flex-col gap-5 lg:sticky lg:top-24 lg:self-start">
        <div class="card flex flex-col gap-4 p-6">
            <div class="flex items-center justify-between"><span class="text-sm font-semibold text-muted">Fee</span><span class="font-extrabold text-forest-900">{{ $comingSoon && $service->slug === 'pay-bills' ? 'Shown at checkout' : $service->feeLabel() }}</span></div>
            <div class="flex items-center justify-between"><span class="text-sm font-semibold text-muted">Partner</span><span class="font-bold text-gold-600">{{ $service->providerName() }}</span></div>
            <div class="flex items-center justify-between"><span class="text-sm font-semibold text-muted">Status</span>@if($comingSoon)<span class="chip chip-gold">Coming soon</span>@else<span class="chip chip-green">Open</span>@endif</div>
            @if (! $comingSoon)
                <a href="{{ $cta[0] }}" class="btn btn-gold w-full">{{ $cta[1] }}</a>
            @else
                <a href="{{ route('notices') }}#subscribe" class="btn btn-outline w-full">Tell me when it opens</a>
            @endif
        </div>
        @php $providers = $service->providerPartners()->filter(fn ($p) => $p->logoUrl()); @endphp
        @if ($providers->count() === 1)
            <div class="card flex items-center justify-center p-6"><img src="{{ $providers->first()->logoUrl() }}" alt="{{ $providers->first()->name }}" class="max-h-16 object-contain" loading="lazy"></div>
        @elseif ($providers->count() > 1)
            <div class="card flex flex-col gap-3 p-5">
                <p class="text-sm font-semibold text-muted">Choose your firm when you open your file</p>
                <ul class="grid grid-cols-2 gap-3">
                    @foreach ($providers as $p)
                        <li class="flex h-16 items-center justify-center rounded-[8px] border border-line bg-white p-2"><img src="{{ $p->logoUrl() }}" alt="{{ $p->name }}" class="max-h-11 max-w-full object-contain" loading="lazy"></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </aside>
</div>
@endsection
