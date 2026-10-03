<?php

namespace App\Integrations\Fidelity;

use Illuminate\Support\Facades\Http;

/**
 * Production adapter. Endpoint paths are placeholders until Fidelity Life IT
 * confirms the contract (see FSPRA-BUILD-SPEC.md section 14).
 */
class HttpFidelityClient implements FidelityClient
{
    private function http()
    {
        return Http::baseUrl((string) config('fspra.fidelity.base_url'))
            ->withToken((string) config('fspra.fidelity.api_key'))
            ->acceptJson()->timeout(15)->retry(2, 500);
    }

    public function match(string $nationalId, string $standNumber): array
    {
        return $this->http()->post('/residents/match', ['national_id' => $nationalId, 'stand_number' => $standNumber])->throw()->json();
    }

    public function paymentHistory(string $reference): array
    {
        return $this->http()->get("/residents/{$reference}/payments")->throw()->json('data', []);
    }

    public function agreementPdf(string $reference): string
    {
        return $this->http()->accept('application/pdf')->get("/residents/{$reference}/agreement")->throw()->body();
    }

    public function requestReplacement(string $reference, string $requestReference): bool
    {
        return $this->http()->post("/residents/{$reference}/agreement/replacements", ['reference' => $requestReference])->successful();
    }
}
