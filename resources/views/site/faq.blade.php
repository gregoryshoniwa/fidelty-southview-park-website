@extends('layouts.site', ['title' => 'Questions and answers', 'description' => 'Answers to common questions about verifying your stand, title deeds, fees, privacy and the association.', 'breadcrumbs' => [['Home', url('/')], ['FAQ', route('faq')]]])
@push('jsonld')
<script type="application/ld+json" nonce="{{ app('csp-nonce') }}">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqs->flatten()->map(fn ($f) => ['@type' => 'Question', 'name' => $f->question, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($f->answer)]])->values()], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
@section('content')
@include('partials.page-hero', ['eyebrow' => 'Help', 'heading' => 'Questions and answers', 'lead' => 'Cannot find it? Ask the assistant in the corner, or write to the committee privately.', 'crumbs' => [['FAQ', null]]])
<div class="wrap max-w-3xl py-14">
    @foreach ($faqs as $topic => $items)
        <h2 class="mb-4 mt-10 font-serif text-2xl font-bold capitalize text-forest-900 first:mt-0">{{ str_replace('-', ' ', $topic) }}</h2>
        <div class="card divide-y divide-line">
            @foreach ($items as $f)
                <details id="faq-{{ $f->id }}" class="group p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-bold text-forest-900">{{ $f->question }}<x-lucide name="plus" class="size-5 text-gold-600 transition group-open:rotate-45" /></summary>
                    <div class="prose-site mt-3 text-[15px] text-muted">{!! nl2br(e($f->answer)) !!}</div>
                </details>
            @endforeach
        </div>
    @endforeach
</div>
@endsection
