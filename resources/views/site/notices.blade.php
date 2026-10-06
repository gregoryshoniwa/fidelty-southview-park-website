@extends('layouts.site', ['title' => 'Notice board', 'description' => 'Official, dated and signed notices from the Fidelity Southview Park Residents Association.', 'breadcrumbs' => [['Home', url('/')], ['Notices', route('notices')]]])
@section('content')
@include('partials.page-hero', ['eyebrow' => 'Notice board', 'heading' => 'Official notices', 'lead' => 'Dated, signed by a committee role, and never mixed with adverts.', 'crumbs' => [['Notices', null]]])
<div class="wrap grid gap-10 py-14 lg:grid-cols-[1fr_300px]">
    <div id="list" class="scroll-mt-24">
        @if ($tabs)
        <nav class="mb-6 flex flex-wrap gap-2" aria-label="Filter notices">
            <a href="{{ route('notices') }}#list" @class(['btn btn-sm', 'btn-green' => !$category, 'btn-outline' => $category])>All</a>
            @foreach ($tabs as $k => $l)
                <a href="{{ route('notices', ['category' => $k]) }}#list" @class(['btn btn-sm', 'btn-green' => $category === $k, 'btn-outline' => $category !== $k])>{{ $l }}</a>
            @endforeach
        </nav>
        @endif
        <div class="card divide-y divide-line px-5 sm:px-6">
            @forelse ($notices as $n) @include('partials.notice-row', ['n' => $n]) @empty <p class="py-10 text-center text-muted">No notices in this category yet.</p> @endforelse
        </div>
        <div class="mt-6">{{ $notices->fragment('list')->links() }}</div>
    </div>
    <aside class="flex flex-col gap-6">
        @if ($channel = config('fspra.whatsapp.channel_url'))
        <div id="subscribe" class="card scroll-mt-24 p-6">
            <h2 class="font-serif text-xl font-bold text-forest-900">Get notices on WhatsApp</h2>
            <p class="mt-1 text-sm text-muted">Official notices only, on the association's WhatsApp channel. Other followers cannot see your number. Unfollow any time.</p>
            <a href="{{ $channel }}" target="_blank" rel="noopener" class="btn btn-gold mt-4 w-full"><x-lucide name="message-circle" class="size-4" />Follow the channel</a>
        </div>
        @endif
        <x-ad placement="medium_rect" :ad="$rect" class="hidden lg:flex" />
    </aside>
</div>
@endsection
