@extends('layouts.site', ['title' => $notice->title, 'description' => $notice->excerpt ?? \Illuminate\Support\Str::limit(strip_tags(\Illuminate\Support\Str::sanitizeHtml((string) $notice->body)), 155), 'ogType' => 'article', 'breadcrumbs' => [['Home', url('/')], ['Notices', route('notices')], [$notice->title, route('notice', $notice)]]])
@push('jsonld')
<script type="application/ld+json" nonce="{{ app('csp-nonce') }}">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'NewsArticle', 'headline' => $notice->title, 'datePublished' => $notice->published_at->toAtomString(), 'dateModified' => $notice->updated_at->toAtomString(), 'author' => ['@type' => 'Organization', 'name' => 'Fidelity Southview Park Residents Association', 'url' => url('/')], 'publisher' => ['@id' => url('/#org')], 'image' => [asset('images/og-default.jpg')], 'mainEntityOfPage' => route('notice', $notice)], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
@section('content')
@include('partials.page-hero', ['eyebrow' => (\App\Models\Notice::CATEGORIES[$notice->category] ?? 'Notice').' · '.$notice->published_at->format('j F Y'), 'heading' => $notice->title, 'crumbs' => [['Notices', route('notices')], [\Illuminate\Support\Str::limit($notice->title, 40), null]]])
<div class="wrap grid gap-12 py-14 lg:grid-cols-[1fr_320px]">
    <article class="prose-site max-w-3xl">
        {!! \Illuminate\Support\Str::sanitizeHtml((string) $notice->body) !!}
        @if ($notice->signed_by_role)<p class="mt-8 border-t border-line pt-4 text-sm font-semibold text-muted">Signed: {{ $notice->signed_by_role }}, Fidelity Southview Park Residents Association</p>@endif
    </article>
    <aside>
        <h2 class="mb-3 font-serif text-xl font-bold text-forest-900">More notices</h2>
        <div class="card divide-y divide-line px-5">@foreach ($more as $n) @include('partials.notice-row', ['n' => $n]) @endforeach</div>
    </aside>
</div>
@endsection
