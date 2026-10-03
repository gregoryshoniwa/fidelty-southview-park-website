@extends('layouts.site', ['title' => 'Test checkout', 'noindex' => true])
@section('content')
<div class="wrap max-w-md py-16">
    <div class="card overflow-hidden">
        <div class="bg-forest-900 p-5 text-cream"><p class="eyebrow text-gold-500">Simulated TN CyberTech Bank checkout</p><p class="mt-1 text-sm text-cream/80">Development only. No money moves.</p></div>
        <div class="flex flex-col gap-3 p-6 text-sm">
            <div class="flex justify-between"><span class="text-muted">For</span><span class="font-bold">{{ \App\Models\Payment::BILLERS[$payment->biller_code]['label'] }}</span></div>
            <div class="flex justify-between"><span class="text-muted">Reference</span><span class="font-bold">{{ $payment->biller_reference }}</span></div>
            <div class="flex justify-between text-base"><span class="text-muted">Total</span><span class="font-extrabold">{{ $payment->currency }} {{ $payment->total }}</span></div>
            <form method="POST" action="{{ route('dev.checkout.pay', $payment->ulid) }}" class="mt-4 grid grid-cols-2 gap-2">
                @csrf <input type="hidden" name="return" value="{{ $return }}">
                <button name="outcome" value="pay" class="btn btn-gold">Pay</button>
                <button name="outcome" value="fail" class="btn btn-outline">Decline</button>
            </form>
        </div>
    </div>
</div>
@endsection
