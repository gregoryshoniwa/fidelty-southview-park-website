@extends('layouts.site', ['title' => 'Services', 'description' => 'Thirteen services for Southview Park residents: verify your stand, Agreement of Sale, title deed tracker, bills, loans, schools, security and more.', 'breadcrumbs' => [['Home', url('/')], ['Services', route('services')]]])
@section('content')
@include('partials.page-hero', ['eyebrow' => 'Services', 'heading' => 'Everything the association does, in one place', 'lead' => 'Phase 1 services are open now. Phase 2 and 3 open as partners sign and each phase proves itself.', 'crumbs' => [['Services', null]]])
<div class="wrap py-16">
    @foreach ($services->groupBy('phase') as $phase => $group)
        <section class="mb-14" aria-labelledby="phase-{{ $phase }}">
            <div class="mb-6 flex items-center gap-3">
                <h2 id="phase-{{ $phase }}" class="font-serif text-2xl font-bold text-forest-900">{{ ['1' => 'Open now', '2' => 'Coming next', '3' => 'Later'][$phase] ?? 'Phase '.$phase }}</h2>
                <span class="chip {{ $phase == 1 ? 'chip-green' : 'chip-grey' }}">Phase {{ $phase }}</span>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($group as $s)<x-service-card :service="$s" />@endforeach
            </div>
        </section>
    @endforeach
</div>
@endsection
