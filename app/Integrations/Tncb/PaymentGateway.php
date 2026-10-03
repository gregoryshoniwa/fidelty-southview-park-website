<?php

namespace App\Integrations\Tncb;

use App\Models\Payment;

interface PaymentGateway
{
    /** @return array{reference: string, checkout_url: string} */
    public function createCheckout(Payment $payment, string $returnUrl): array;

    /** @return array{status: string, token?: string} */
    public function queryStatus(string $gatewayReference): array;

    public function verifySignature(string $payload, ?string $signature): bool;
}
