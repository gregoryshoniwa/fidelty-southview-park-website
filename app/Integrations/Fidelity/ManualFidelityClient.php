<?php

namespace App\Integrations\Fidelity;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Used until Fidelity Life's API is live: every verification goes to the committee,
 * who confirm it against Fidelity's records and approve it in the admin panel.
 */
class ManualFidelityClient implements FidelityClient
{
    public function match(string $nationalId, string $standNumber): array
    {
        return ['matched' => true, 'manual' => true, 'reference' => null];
    }

    public function paymentHistory(string $reference): array
    {
        return [];
    }

    public function agreementPdf(string $reference): string
    {
        throw new HttpException(409, 'Digital agreements open when Fidelity Life connects its records. Request a replacement instead.');
    }

    public function requestReplacement(string $reference, string $requestReference): bool
    {
        return true;
    }
}
