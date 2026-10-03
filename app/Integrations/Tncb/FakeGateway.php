<?php

namespace App\Integrations\Tncb;

use App\Models\Payment;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Local simulator of TN CyberTech Bank hosted checkout. The checkout URL opens
 * a page in this app that posts a correctly signed webhook back, exactly like
 * the bank will.
 */
class FakeGateway implements PaymentGateway
{
    public function createCheckout(Payment $payment, string $returnUrl): array
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('The simulated gateway cannot be used in production. Set TNCB_DRIVER=http.');
        }
        $ref = 'TNCB-'.strtoupper(Str::random(12));

        return [
            'reference' => $ref,
            'checkout_url' => URL::temporarySignedRoute('dev.checkout', now()->addMinutes(30), ['payment' => $payment->ulid, 'ref' => $ref, 'return' => $returnUrl]),
        ];
    }

    public function queryStatus(string $gatewayReference): array
    {
        return ['status' => 'pending'];
    }

    public function verifySignature(string $payload, ?string $signature): bool
    {
        if (strlen((string) config('fspra.tncb.webhook_secret')) < 32) {
            return false; // never accept webhooks signed with a weak or empty secret
        }
        $expected = hash_hmac('sha256', $payload, (string) config('fspra.tncb.webhook_secret'));

        return $signature !== null && hash_equals($expected, $signature);
    }

    public static function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) config('fspra.tncb.webhook_secret'));
    }
}
