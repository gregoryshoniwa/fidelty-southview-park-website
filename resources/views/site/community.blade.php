@extends('layouts.site', ['title' => 'Community directory', 'description' => 'Businesses, churches and schools serving Fidelity Southview Park, Amalinda.', 'breadcrumbs' => [['Home', url('/')], ['Community', route('community')]]])
@section('content')
@include('partials.page-hero', ['eyebrow' => 'Community', 'heading' => 'Businesses, churches and schools', 'lead' => 'Pages marked Listing are public information only. Partner pages are run by the organisation itself.', 'crumbs' => [['Community', null]]])
<div class="wrap py-14">
    <nav class="mb-8 flex flex-wrap gap-2" aria-label="Filter">
        <a href="{{ route('community') }}" @class(['btn btn-sm', 'btn-green' => !$type, 'btn-outline' => $type])>All</a>
        @foreach (\App\Models\CommunityPage::TYPES as $k => $l)
            <a href="{{ route('community', ['type' => $k]) }}" @class(['btn btn-sm', 'btn-green' => $type === $k, 'btn-outline' => $type !== $k])>{{ $l }}es</a>
        @endforeach
    </nav>
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($pages as $p)
            <a href="{{ route('community.page', $p) }}" class="card card-hover flex flex-col overflow-hidden">
                <img src="{{ $p->cover_path ? asset($p->cover_path) : '/images/covers/'.$p->type.'.webp' }}" alt="" width="400" height="240" loading="lazy" decoding="async" class="aspect-[5/3] w-full object-cover">
                <span class="flex flex-1 flex-col gap-1.5 p-5">
                    <span class="flex items-center gap-2">
                        <span class="chip {{ $p->type === 'school' ? 'chip-green' : ($p->type === 'church' ? 'chip-gold' : 'chip-grey') }}">{{ \App\Models\CommunityPage::TYPES[$p->type] }}</span>
                        @if ($p->verified)<span class="chip chip-green"><x-lucide name="badge-check" class="size-3" />Partner</span>@else<span class="chip chip-grey">Listing</span>@endif
                    </span>
                    <span class="text-lg font-extrabold text-forest-900">{{ $p->name }}</span>
                    <span class="text-sm text-muted">{{ $p->tagline }}</span>
                    <span class="mt-auto pt-2 text-xs font-semibold text-muted">{{ $p->address }}</span>
                </span>
            </a>
        @endforeach
        @foreach ($tiles as $t)
            <a href="{{ route('ad.click', $t) }}" data-ad="{{ $t->id }}" rel="sponsored noopener" class="card card-hover flex flex-col overflow-hidden">
                <img src="{{ $t->creative_path ? asset($t->creative_path) : '/images/covers/business.webp' }}" alt="" loading="lazy" class="aspect-[5/3] w-full object-cover">
                <span class="flex flex-col gap-1.5 p-5"><span class="sponsored-label">{{ $t->advertiser === 'Southview Park Residents Association' ? 'From the association' : 'Sponsored' }}</span><span class="text-lg font-extrabold text-forest-900">{{ $t->headline }}</span><span class="text-sm text-muted">{{ $t->body }}</span></span>
            </a>
        @endforeach
    </div>
    <div class="mt-8">{{ $pages->links() }}</div>
</div>
@endsection
