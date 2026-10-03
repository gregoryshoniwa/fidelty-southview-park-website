<?php

namespace App\Integrations\Tncb;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;

/**
 * Production adapter for TN CyberTech Bank hosted checkout. Paths are
 * placeholders until the bank shares its merchant API specification.
 */
class HttpGateway implements PaymentGateway
{
    private function http()
    {
        return Http::baseUrl((string) config('fspra.tncb.base_url'))
            ->withToken((string) config('fspra.tncb.api_key'))
            ->acceptJson()->timeout(20);
    }

    public function createCheckout(Payment $payment, string $returnUrl): array
    {
        $res = $this->http()->post('/checkout/sessions', [
            'merchant_id' => config('fspra.tncb.merchant_id'),
            'merchant_reference' => $payment->ulid,
            'amount' => (string) $payment->total,
            'currency' => $payment->currency,
            'biller_code' => $payment->biller_code,
            'biller_reference' => $payment->biller_reference,
            'return_url' => $returnUrl,
            'callback_url' => route('webhooks.tncb'),
        ])->throw()->json();

        return ['reference' => $res['reference'], 'checkout_url' => $res['checkout_url']];
    }

    public function queryStatus(string $gatewayReference): array
    {
        return $this->http()->get("/checkout/sessions/{$gatewayReference}")->throw()->json();
    }

    public function verifySignature(string $payload, ?string $signature): bool
    {
        $expected = hash_hmac('sha256', $payload, (string) config('fspra.tncb.webhook_secret'));

        return $signature !== null && hash_equals($expected, $signature);
    }
}
