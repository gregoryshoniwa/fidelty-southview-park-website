@extends('layouts.site', ['title' => $page->name, 'description' => $page->tagline ?? \Illuminate\Support\Str::limit((string) $page->description, 155), 'breadcrumbs' => [['Home', url('/')], ['Community', route('community')], [$page->name, route('community.page', $page)]]])
@push('jsonld')
<script type="application/ld+json" nonce="{{ app('csp-nonce') }}">{!! json_encode(array_filter(['@context' => 'https://schema.org', '@type' => ['school' => 'School', 'church' => 'Church', 'business' => 'LocalBusiness'][$page->type] ?? 'Organization', 'name' => $page->name, 'description' => $page->description, 'telephone' => $page->phone, 'address' => $page->address ? ['@type' => 'PostalAddress', 'streetAddress' => $page->address, 'addressLocality' => 'Harare', 'addressCountry' => 'ZW'] : null, 'url' => route('community.page', $page)]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
@section('content')
<section class="relative bg-forest-900 text-cream">
    <img src="{{ $page->cover_path ? asset($page->cover_path) : '/images/covers/'.$page->type.'.webp' }}" alt="" class="absolute inset-0 size-full object-cover opacity-30">
    <div class="wrap relative py-16">
        <p class="eyebrow text-gold-500">{{ \App\Models\CommunityPage::TYPES[$page->type] }} · {{ $page->verified ? 'Partner page' : 'Public listing' }}</p>
        <h1 class="mt-2 font-serif text-4xl font-bold sm:text-5xl">{{ $page->name }}</h1>
        @if ($page->tagline)<p class="mt-3 max-w-2xl text-lg text-cream/85">{{ $page->tagline }}</p>@endif
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="/app/community/{{ $page->slug }}" class="btn btn-gold"><x-lucide name="bell" class="size-4" />Follow in the app</a>
            <span class="btn btn-ghost pointer-events-none">{{ $page->followers_count }} followers</span>
        </div>
    </div>
</section>
<div class="wrap grid gap-10 py-14 lg:grid-cols-[1fr_320px]">
    <div class="flex flex-col gap-8">
        @if (!$page->verified)
            <div class="flex gap-3 rounded-[12px] border border-line bg-white p-5 text-sm text-muted"><x-lucide name="info" class="size-5 text-forest-700" /><p>This is a public listing. {{ $page->name }} is not yet a partner of the association, and details come from public sources. If you run this {{ $page->type }}, <a href="/app/inbox/new" class="font-bold text-forest-700 underline">write to us</a> to manage this page.</p></div>
        @endif
        @if ($page->description)<div class="prose-site">{!! nl2br(e($page->description)) !!}</div>@endif
        @if ($products->isNotEmpty())
            <section><h2 class="mb-4 font-serif text-2xl font-bold text-forest-900">Shop</h2>
                <div class="grid gap-4 sm:grid-cols-2">@foreach ($products as $p)<div class="card p-5"><p class="font-extrabold text-forest-900">{{ $p->name }}</p><p class="text-sm text-muted">{{ $p->description }}</p><p class="mt-2 font-bold text-gold-600">{{ $p->currency }} {{ number_format($p->price, 2) }}</p></div>@endforeach</div>
            </section>
        @endif
        @if ($posts->isNotEmpty())
            <section><h2 class="mb-4 font-serif text-2xl font-bold text-forest-900">Updates</h2>
                <div class="flex flex-col gap-4">@foreach ($posts as $post)<article class="card p-5"><time class="text-xs font-bold text-muted" datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('j M Y') }}</time><p class="mt-2 whitespace-pre-line text-[15px]">{{ $post->body }}</p></article>@endforeach</div>
            </section>
        @endif
    </div>
    <aside class="card flex flex-col gap-3 self-start p-6 text-sm">
        @if ($page->address)<p class="flex gap-2"><x-lucide name="map-pin" class="size-4 text-forest-700" />{{ $page->address }}</p>@endif
        @if ($page->phone)<p class="flex gap-2"><x-lucide name="phone" class="size-4 text-forest-700" />{{ $page->phone }}</p>@endif
        @if ($page->hours)<div class="flex gap-2"><x-lucide name="clock" class="size-4 text-forest-700" /><ul>@foreach ($page->hours as $h)<li>{{ $h }}</li>@endforeach</ul></div>@endif
    </aside>
</div>
@endsection
