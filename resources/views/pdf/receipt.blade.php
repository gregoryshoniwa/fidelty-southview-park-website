<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;color:#14201a;font-size:12px;margin:0;padding:32px}
.head{background:#073320;color:#faf7f0;padding:20px 24px;border-radius:6px}.gold{color:#c9a227}
table{width:100%;border-collapse:collapse;margin-top:20px}td{padding:9px 0;border-bottom:1px solid #e6e1d6}td.r{text-align:right;font-weight:bold}
.total td{border-top:2px solid #073320;border-bottom:0;font-size:15px}.muted{color:#4c5a52;font-size:10px}
</style></head><body>
<div class="head"><div style="font-size:18px;font-weight:bold">Payment receipt</div><div class="gold" style="font-size:10px;letter-spacing:2px">FIDELITY SOUTHVIEW PARK RESIDENTS ASSOCIATION</div></div>
<table>
<tr><td>Receipt number</td><td class="r">{{ $payment->ulid }}</td></tr>
<tr><td>Date paid</td><td class="r">{{ $payment->paid_at?->timezone('Africa/Harare')->format('j F Y, H:i') }}</td></tr>
<tr><td>Paid by</td><td class="r">{{ $payment->resident?->user?->name }}{{ $payment->resident?->stand ? ', stand '.$payment->resident->stand->stand_number : '' }}</td></tr>
<tr><td>For</td><td class="r">{{ \App\Models\Payment::BILLERS[$payment->biller_code]['label'] ?? $payment->biller_code }}</td></tr>
<tr><td>{{ \App\Models\Payment::BILLERS[$payment->biller_code]['reference'] ?? 'Reference' }}</td><td class="r">{{ $payment->biller_reference }}</td></tr>
@if($payment->token_or_voucher)<tr><td>Token</td><td class="r" style="font-size:15px">{{ $payment->token_or_voucher }}</td></tr>@endif
<tr><td>Amount</td><td class="r">{{ $payment->currency }} {{ number_format($payment->amount, 2) }}</td></tr>
<tr><td>Platform fee</td><td class="r">{{ $payment->currency }} {{ number_format($payment->platform_fee, 2) }}</td></tr>
<tr class="total"><td>Total paid</td><td class="r">{{ $payment->currency }} {{ number_format($payment->total, 2) }}</td></tr>
<tr><td>Bank reference</td><td class="r">{{ $payment->gateway_reference }}</td></tr>
</table>
<p class="muted" style="margin-top:28px">Processed by TN CyberTech Bank. The association never holds your money. Questions: write to the committee in the app quoting the receipt number. {{ config('fspra.site_domain') }}</p>
</body></html>
