<?php

namespace App\Services;

use App\Integrations\Tncb\PaymentGateway;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Resident;
use App\Models\SchoolInvoice;
use App\Models\ServiceRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private PaymentGateway $gateway,
        private LedgerService $ledger,
        private NotificationService $notify,
    ) {}

    /** @return array{biller: string, label: string, amount: float, platform_fee: float, total: float, currency: string} */
    public function quote(string $biller, float $amount, string $currency = 'USD'): array
    {
        $cfg = Payment::BILLERS[$biller] ?? null;
        if (! $cfg) {
            throw ValidationException::withMessages(['biller_code' => 'Unknown biller.']);
        }
        if ($amount < 0.5 || $amount > 20000) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount between 0.50 and 20 000.']);
        }
        $fee = round((float) $cfg['fee'], 2);

        return [
            'biller' => $biller,
            'label' => $cfg['label'],
            'reference_label' => $cfg['reference'],
            'amount' => round($amount, 2),
            'platform_fee' => $fee,
            'total' => round($amount + $fee, 2),
            'currency' => $currency,
        ];
    }

    public function create(?Resident $resident, string $biller, string $reference, float $amount, string $currency = 'USD', ?ServiceRequest $request = null, string $purpose = 'bill'): Payment
    {
        $q = $this->quote($biller, $amount, $currency);
        $commissionPct = (float) Payment::BILLERS[$biller]['commission_percent'];

        return Payment::create([
            'resident_id' => $resident?->id,
            'service_request_id' => $request?->id,
            'partner_id' => $request?->partner_id,
            'purpose' => $purpose,
            'biller_code' => $biller,
            'biller_reference' => $reference,
            'amount' => $q['amount'],
            'platform_fee' => $q['platform_fee'],
            'commission' => round($q['amount'] * $commissionPct / 100, 2),
            'total' => $q['total'],
            'currency' => $currency,
            'status' => 'initiated',
        ]);
    }

    public function checkout(Payment $payment, string $returnUrl): string
    {
        $res = $this->gateway->createCheckout($payment, $returnUrl);
        $payment->update(['gateway_reference' => $res['reference'], 'status' => 'pending']);
        AuditLog::record('payment.checkout_created', $payment, ['ref' => $res['reference']]);

        return $res['checkout_url'];
    }

    /** Idempotent handler for the bank webhook. */
    public function handleWebhook(array $data): ?Payment
    {
        return DB::transaction(function () use ($data) {
            $payment = Payment::where('gateway_reference', $data['reference'] ?? '')->lockForUpdate()->first();
            if (! $payment || in_array($payment->status, ['paid', 'refunded'], true)) {
                return $payment;
            }
            if (($data['status'] ?? '') === 'paid' && config('fspra.tncb.driver') === 'http') {
                // Confirm with the bank before releasing tokens or marking bills paid.
                $confirmed = $this->gateway->queryStatus($payment->gateway_reference);
                if (($confirmed['status'] ?? '') !== 'paid') {
                    AuditLog::record('payment.unconfirmed_webhook', $payment);

                    return $payment;
                }
            }
            if (($data['status'] ?? '') !== 'paid') {
                $payment->update(['status' => 'failed', 'gateway_payload' => $data]);
                AuditLog::record('payment.failed', $payment);

                return $payment;
            }
            if (bccomp((string) $data['amount'], (string) $payment->total, 2) !== 0 || ($data['currency'] ?? '') !== $payment->currency) {
                $payment->update(['status' => 'failed', 'gateway_payload' => $data]);
                AuditLog::record('payment.amount_mismatch', $payment, ['received' => $data['amount'] ?? null]);

                return $payment;
            }

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'gateway_payload' => $data,
                'token_or_voucher' => $data['token'] ?? ($payment->biller_code === 'zesa' ? $this->fakeToken() : null),
            ]);

            $label = Payment::BILLERS[$payment->biller_code]['label'];
            if ((float) $payment->platform_fee > 0) {
                $this->ledger->post(['type' => 'fee', 'description' => "Platform fee: {$label}", 'amount' => $payment->platform_fee, 'currency' => $payment->currency, 'payment_id' => $payment->id, 'partner_id' => $payment->partner_id, 'source' => 'gateway_webhook']);
            }
            if ((float) $payment->commission > 0) {
                $this->ledger->post(['type' => 'commission', 'description' => "Commission: {$label}", 'amount' => $payment->commission, 'currency' => $payment->currency, 'payment_id' => $payment->id, 'partner_id' => $payment->partner_id, 'source' => 'gateway_webhook']);
            }

            $this->afterPaid($payment);
            dispatch(fn () => app(self::class)->generateReceipt($payment->fresh()))->afterCommit();
            AuditLog::record('payment.paid', $payment);

            return $payment;
        });
    }

    private function afterPaid(Payment $payment): void
    {
        if ($payment->request) {
            $payment->request->events()->create(['actor_type' => 'system', 'type' => 'payment', 'payload' => ['amount' => (string) $payment->amount, 'currency' => $payment->currency, 'biller' => $payment->biller_code]]);
            if ($payment->request->status === 'waiting_payment') {
                $payment->request->update(['status' => 'waiting_partner']);
            }
        }
        SchoolInvoice::where('payment_id', $payment->id)->update(['status' => 'paid']);
        Order::where('payment_id', $payment->id)->update(['status' => 'paid']);

        if ($payment->resident) {
            $extra = $payment->token_or_voucher ? " Token: {$payment->token_or_voucher}." : '';
            $this->notify->notify($payment->resident->user, 'Payment received', Payment::BILLERS[$payment->biller_code]['label'].' '.$payment->currency.' '.$payment->total.'.'.$extra, '/app/receipts', 'payment');
        }
    }

    public function generateReceipt(Payment $payment): string
    {
        $pdf = Pdf::loadView('pdf.receipt', ['payment' => $payment->loadMissing('resident.stand', 'resident.user')]);
        $path = 'receipts/'.$payment->ulid.'.pdf';
        Storage::disk('local')->put($path, $pdf->output());
        $payment->update(['receipt_path' => $path]);

        return $path;
    }

    private function fakeToken(): string
    {
        return implode('-', str_split(str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT).str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT).random_int(1000, 9999), 4));
    }
}
