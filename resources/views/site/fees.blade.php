@extends('layouts.site', ['title' => 'Fees and commissions', 'description' => 'Every fee the association charges, printed in full.'])
@section('content')
@include('partials.page-hero', ['eyebrow' => 'Fees', 'heading' => 'Every fee, printed in full', 'lead' => 'You never pay more through the platform than at the counter. Fees change only by committee resolution, announced 14 days in advance.', 'crumbs' => [['Fees', null]]])
<div class="wrap py-14">
    <div class="card overflow-x-auto">
        <table class="w-full min-w-[560px] text-left text-sm">
            <thead><tr class="border-b border-forest-900 text-xs uppercase tracking-wider text-muted"><th class="p-4">Service</th><th class="p-4">Partner</th><th class="p-4">Fee to you</th><th class="p-4">Status</th></tr></thead>
            <tbody class="divide-y divide-line">
                @foreach ($services as $s)
                    <tr><td class="p-4 font-bold text-forest-900"><a href="{{ route('service', $s) }}" class="hover:underline">{{ $s->name }}</a></td><td class="p-4 text-muted">{{ $s->providerName('Association') }}</td><td class="p-4">{{ $s->feeLabel() }}</td><td class="p-4">@if(($s->slug === 'pay-bills' && !config('fspra.payments_live')) || $s->phase > 1)<span class="chip chip-gold">Coming soon</span>@else<span class="chip chip-green">Open</span>@endif</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
