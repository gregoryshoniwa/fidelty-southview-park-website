<?php

namespace App\Integrations\Firebase;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Verifies Firebase Authentication ID tokens without a service account:
 * RS256 signature against Google's published keys, plus issuer, audience,
 * expiry, issued-at and auth_time checks as Firebase documents.
 */
class TokenVerifier
{
    private const CERTS = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    /** @param  array<string,string>|null  $keys  kid => PEM, injected in tests */
    public function __construct(private ?array $keys = null) {}

    /** @return array<string,mixed> verified claims */
    public function verify(string $idToken): array
    {
        $project = (string) config('fspra.firebase.project_id');
        if ($project === '') {
            throw ValidationException::withMessages(['token' => 'Google and email sign-in are not configured yet.']);
        }
        $keys = collect($this->keys ?? $this->googleKeys())->map(fn ($pem) => new Key($pem, 'RS256'))->all();
        JWT::$leeway = 30;
        try {
            $claims = (array) JWT::decode($idToken, $keys);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['token' => 'Your sign-in could not be confirmed. Please try again.']);
        }
        $now = time();
        $ok = ($claims['iss'] ?? null) === 'https://securetoken.google.com/'.$project
            && ($claims['aud'] ?? null) === $project
            && is_string($claims['sub'] ?? null) && $claims['sub'] !== '' && strlen($claims['sub']) <= 128
            && ($claims['auth_time'] ?? PHP_INT_MAX) <= $now + 30
            && ($claims['iat'] ?? PHP_INT_MAX) <= $now + 30
            && ($claims['auth_time'] ?? 0) >= $now - 600; // must be a fresh sign-in, not a replayed old token
        if (! $ok) {
            throw ValidationException::withMessages(['token' => 'Your sign-in could not be confirmed. Please try again.']);
        }
        $claims['firebase'] = (array) ($claims['firebase'] ?? []);

        return $claims;
    }

    private function googleKeys(): array
    {
        return Cache::remember('firebase:certs', 3600, function () {
            $res = Http::timeout(10)->get(self::CERTS)->throw();

            return $res->json();
        });
    }
}
