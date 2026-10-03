@extends('layouts.site', ['title' => 'Notice board', 'description' => 'Official, dated and signed notices from the Fidelity Southview Park Residents Association.', 'breadcrumbs' => [['Home', url('/')], ['Notices', route('notices')]]])
@section('content')
@include('partials.page-hero', ['eyebrow' => 'Notice board', 'heading' => 'Official notices', 'lead' => 'Dated, signed by a committee role, and never mixed with adverts.', 'crumbs' => [['Notices', null]]])
<div class="wrap grid gap-10 py-14 lg:grid-cols-[1fr_300px]">
    <div>
        <nav class="mb-6 flex flex-wrap gap-2" aria-label="Filter notices">
            <a href="{{ route('notices') }}" @class(['btn btn-sm', 'btn-green' => !$category, 'btn-outline' => $category])>All</a>
            @foreach (\Illuminate\Support\Arr::except(\App\Models\Notice::CATEGORIES, ['sponsored']) as $k => $l)
                <a href="{{ route('notices', ['category' => $k]) }}" @class(['btn btn-sm', 'btn-green' => $category === $k, 'btn-outline' => $category !== $k])>{{ $l }}</a>
            @endforeach
        </nav>
        <div class="card divide-y divide-line px-5 sm:px-6">
            @forelse ($notices as $n) @include('partials.notice-row', ['n' => $n]) @empty <p class="py-10 text-center text-muted">No notices in this category yet.</p> @endforelse
        </div>
        <div class="mt-6">{{ $notices->links() }}</div>
    </div>
    <aside class="flex flex-col gap-6">
        <div id="subscribe" class="card p-6">
            <h2 class="font-serif text-xl font-bold text-forest-900">Get notices by SMS</h2>
            <p class="mt-1 text-sm text-muted">Official notices only. Reply STOP any time.</p>
            <form method="POST" action="{{ route('subscribe') }}" class="mt-4 flex flex-col gap-3">
                @csrf
                <label for="n-phone" class="sr-only">Mobile number</label>
                <input id="n-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="077 123 4567" class="input">
                @error('phone')<p class="error">{{ $message }}</p>@enderror
                <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                <button class="btn btn-gold">Subscribe</button>
            </form>
        </div>
        <x-ad placement="medium_rect" :ad="$rect" class="hidden lg:flex" />
    </aside>
</div>
@endsection
