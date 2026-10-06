@extends('layouts.site', ['title' => 'About the association', 'description' => 'Who runs the Fidelity Southview Park Residents Association, how decisions are made, the committee, and published minutes.', 'breadcrumbs' => [['Home', url('/')], ['About', route('about')]]])
@section('content')
@include('partials.page-hero', ['eyebrow' => 'About us', 'heading' => 'A service-first association, run by rules you can read', 'lead' => 'Seven founding members run the association for 12 months. After that, verified residents vote to confirm or replace each role.', 'crumbs' => [['About', null]]])
<div class="wrap grid gap-12 py-14 lg:grid-cols-[1fr_340px]">
    <div class="flex flex-col gap-14">
        @if ($page)<article class="prose-site">{!! \Illuminate\Support\Str::sanitizeHtml((string) $page->body) !!}</article>@endif
        <section aria-labelledby="committee-title">
            <h2 id="committee-title" class="mb-6 font-serif text-3xl font-bold text-forest-900">The committee</h2>
            @if ($members->isEmpty())
                <p class="card p-6 text-muted">The founding committee will be named here when the association goes public.</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($members as $m)
                        <div class="card flex items-center gap-4 p-5">
                            <img src="{{ $m->photo_path ? asset($m->photo_path) : '/images/avatar.svg' }}" alt="" width="56" height="56" class="size-14 rounded-full bg-forest-100 object-cover" loading="lazy">
                            <div><p class="font-extrabold text-forest-900">{{ $m->name }}</p><p class="text-sm font-semibold text-gold-600">{{ $m->role }}</p>@if($m->area)<p class="text-xs text-muted">{{ $m->area }}</p>@endif</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
        <section id="minutes" aria-labelledby="minutes-title">
            <h2 id="minutes-title" class="mb-6 font-serif text-3xl font-bold text-forest-900">Minutes</h2>
            <div class="card divide-y divide-line">
                @forelse ($minutes as $m)
                    <a href="{{ route('minute', $m) }}" class="flex items-center justify-between gap-4 p-5 hover:bg-cream"><span><span class="block font-bold text-forest-900">{{ $m->title }}</span><span class="text-sm text-muted">{{ $m->meeting_date->format('j F Y') }}</span></span><x-lucide name="chevron-right" class="size-5 text-gold-600" /></a>
                @empty
                    <p class="p-6 text-muted">Minutes will appear here within 7 days of each meeting.</p>
                @endforelse
            </div>
        </section>
    </div>
    <aside class="flex flex-col gap-5 lg:sticky lg:top-24 lg:self-start">
        <div class="card p-6">
            <h2 class="font-serif text-xl font-bold text-forest-900">Our commitments</h2>
            <ul class="mt-4 flex flex-col gap-3 text-[15px]">
                @foreach (['Fees printed before every payment', 'Two signatories on every payment out', 'Minutes published within 7 days', 'Independent review of the accounts each year', 'Conflict of interest register', 'Committee term ends after 12 months'] as $c)
                    <li class="flex gap-2.5"><x-lucide name="check" class="mt-0.5 size-4 text-forest-700" />{{ $c }}</li>
                @endforeach
            </ul>
            @if (\App\Models\CmsPage::live('constitution'))<a href="{{ route('constitution') }}" class="btn btn-gold mt-5 w-full">Read the constitution</a>@endif
        </div>
        <div class="card grid grid-cols-2 gap-4 p-6">
            <div><p class="font-serif text-3xl font-bold text-forest-700">{{ $stats['open_inbox'] }}</p><p class="text-xs text-muted">Open inbox requests</p></div>
            <div><p class="font-serif text-3xl font-bold text-forest-700">{{ $stats['answered_pct'] !== null ? $stats['answered_pct'].'%' : 'New' }}</p><p class="text-xs text-muted">Answered within 48 h</p></div>
        </div>
    </aside>
</div>
@endsection
