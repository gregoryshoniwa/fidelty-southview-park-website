<?php

namespace App\Integrations\Fidelity;

interface FidelityClient
{
    /** @return array{matched: bool, reference?: string, phone?: string, phone_masked?: string, buyer_name?: string} */
    public function match(string $nationalId, string $standNumber): array;

    /** @return array<int, array{date: string, description: string, amount: float, currency: string, balance: float}> */
    public function paymentHistory(string $reference): array;

    /** Returns raw PDF bytes of the Agreement of Sale. */
    public function agreementPdf(string $reference): string;

    public function requestReplacement(string $reference, string $requestReference): bool;
}
