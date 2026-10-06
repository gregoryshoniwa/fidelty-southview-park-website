@props(['service'])
@php
    $live = $service->phase === 1;
    $isPay = $service->slug === 'pay-bills';
@endphp
<a href="{{ route('service', $service) }}" class="card card-hover group flex flex-col gap-3 p-6">
    <span class="flex size-11 items-center justify-center rounded-[8px] {{ $service->phase === 1 ? 'bg-forest-100 text-forest-700' : 'bg-gold-100 text-gold-600' }}">
        <x-lucide :name="$service->icon" class="size-[22px]" />
    </span>
    <h3 class="font-serif text-xl font-bold text-forest-900">{{ $service->name }}</h3>
    <p class="text-[15px] leading-relaxed text-muted">{{ $service->summary }}</p>
    <span class="mt-auto flex items-center justify-between gap-3 pt-2 text-[13px] font-bold">
        @if ($isPay && !config('fspra.payments_live'))
            <span class="chip chip-gold">Coming soon</span>
        @elseif ($live)
            <span class="text-forest-700">{{ $service->feeLabel() }}</span>
        @else
            <span class="chip chip-grey">Phase {{ $service->phase }}</span>
        @endif
        <span class="text-gold-600">{{ $service->providerName() }}</span>
    </span>
</a>
