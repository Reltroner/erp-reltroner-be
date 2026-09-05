<?php

namespace App\Security\Keycloak;

use Exception;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KeycloakTokenValidator
{
    /**
     * Decode and validate a JWT token.
     *
     * @throws Exception
     */
    public function validateToken(string $token): array
    {
        // 1. Split token to inspect header and claims
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid token format');
        }

        $headerJson = $this->base64UrlDecode($parts[0]);
        $payloadJson = $this->base64UrlDecode($parts[1]);

        if (! $headerJson || ! $payloadJson) {
            throw new Exception('Failed to parse token headers or claims');
        }

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (! is_array($header) || ! is_array($payload)) {
            throw new Exception('Failed to parse token headers or claims');
        }

        // Validate algorithm header against configured allow-list
        if (! isset($header['alg']) || ! is_string($header['alg']) || trim($header['alg']) === '') {
            throw new Exception('Missing or invalid token algorithm');
        }

        $tokenAlg = trim($header['alg']);
        $allowedAlgorithms = $this->getAllowedAlgorithms();

        if (! in_array($tokenAlg, $allowedAlgorithms, true)) {
            throw new Exception("Algorithm '{$tokenAlg}' is not allowed");
        }

        // 2. Validate issuer and resolve realm
        if (empty($payload['iss']) || ! is_string($payload['iss'])) {
            throw new Exception('Missing issuer claim (iss)');
        }

        $iss = $payload['iss'];
        $realm = $this->extractRealm($iss);
        if (! $realm) {
            throw new Exception('Unknown realm structure in token');
        }

        $allowedRealms = $this->getAllowedRealms();
        if (! in_array($realm, $allowedRealms, true)) {
            throw new Exception("Realm '{$realm}' is not allowed");
        }

        $baseUrl = rtrim((string) config('keycloak.base_url', 'https://sso.reltroner.com'), '/');
        $expectedIssuer = "{$baseUrl}/realms/{$realm}";

        if ($iss !== $expectedIssuer) {
            throw new Exception('Invalid token issuer');
        }

        // 3. Resolve validation keys
        $keys = [];
        if (config('keycloak.mock_mode')) {
            $mockPublicKey = config('keycloak.mock_public_key');
            if ($mockPublicKey) {
                // If it is a PEM public key, wrap it.
                if (! str_contains($mockPublicKey, '-----BEGIN PUBLIC KEY-----')) {
                    $mockPublicKey = "-----BEGIN PUBLIC KEY-----\n".wordwrap($mockPublicKey, 64, "\n", true)."\n-----END PUBLIC KEY-----";
                }
                $mockKey = new Key($mockPublicKey, $tokenAlg);
                $kid = isset($header['kid']) && is_string($header['kid']) ? $header['kid'] : 'mock-kid';
                $keys = [$kid => $mockKey, 'mock-kid' => $mockKey];
            } else {
                throw new Exception('Mock mode enabled but no mock public key configured');
            }
        } else {
            $keys = $this->fetchJwksKeys($realm, $allowedAlgorithms);
        }

        // 4. Decode and verify signature & expiry using Firebase JWT library
        // Apply clock leeway
        $originalLeeway = JWT::$leeway;
        JWT::$leeway = config('keycloak.clock_leeway', 60);

        try {
            $decoded = JWT::decode($token, $keys);
            $decodedArray = json_decode(json_encode($decoded), true);
        } catch (Exception $e) {
            Log::warning('JWT signature or expiry check failed: '.$e->getMessage());
            throw new Exception('Invalid token signature or expired: '.$e->getMessage());
        } finally {
            JWT::$leeway = $originalLeeway;
        }

        // 5. Validate audience
        if (empty($decodedArray['aud'])) {
            throw new Exception('Missing audience claim (aud)');
        }

        $expectedAudience = config('keycloak.expected_audience');
        $audiences = is_array($decodedArray['aud']) ? $decodedArray['aud'] : [$decodedArray['aud']];
        if (! in_array($expectedAudience, $audiences, true)) {
            throw new Exception("Wrong audience '{$expectedAudience}' expected");
        }

        return $decodedArray;
    }

    /**
     * Decode a base64url encoded string.
     */
    private function base64UrlDecode(string $input): ?string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(str_replace(['-', '_'], ['+', '/'], $input), true);

        return $decoded !== false ? $decoded : null;
    }

    /**
     * Extract realm name from the iss claim.
     */
    private function extractRealm(string $iss): ?string
    {
        $path = parse_url($iss, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (preg_match('#^/realms/([a-zA-Z0-9_-]+)/?$#', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get normalized list of allowed realms.
     *
     * @return list<string>
     */
    private function getAllowedRealms(): array
    {
        $configured = config('keycloak.allowed_realms', ['reltroner-erp', 'reltroner-admin']);
        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }
        if (! is_array($configured)) {
            $configured = ['reltroner-erp', 'reltroner-admin'];
        }

        $normalized = array_values(array_filter(array_map(function ($realm) {
            return is_string($realm) ? trim($realm) : null;
        }, $configured)));

        return ! empty($normalized) ? $normalized : ['reltroner-erp', 'reltroner-admin'];
    }

    /**
     * Get normalized list of allowed algorithms.
     *
     * @return list<string>
     */
    private function getAllowedAlgorithms(): array
    {
        $configured = config('keycloak.allowed_algorithms', ['RS256']);
        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }
        if (! is_array($configured)) {
            $configured = ['RS256'];
        }

        $normalized = array_values(array_filter(array_map(function ($alg) {
            return is_string($alg) ? strtoupper(trim($alg)) : null;
        }, $configured)));

        return ! empty($normalized) ? $normalized : ['RS256'];
    }

    /**
     * Fetch JWKS keys for a realm.
     *
     * @param  list<string>  $allowedAlgorithms
     * @return array<string, Key>
     *
     * @throws Exception
     */
    private function fetchJwksKeys(string $realm, array $allowedAlgorithms): array
    {
        $cacheKey = "keycloak_jwks_keys_{$realm}";

        return Cache::remember($cacheKey, config('keycloak.jwks_cache_ttl', 3600), function () use ($realm, $allowedAlgorithms) {
            $baseUrl = rtrim((string) config('keycloak.base_url', 'https://sso.reltroner.com'), '/');
            $jwksUrl = "{$baseUrl}/realms/{$realm}/protocol/openid-connect/certs";

            $connectTimeout = (int) config('keycloak.connect_timeout', 5);
            $requestTimeout = (int) config('keycloak.request_timeout', 10);

            try {
                $response = Http::connectTimeout($connectTimeout)
                    ->timeout($requestTimeout)
                    ->get($jwksUrl);

                if (! $response->successful()) {
                    throw new Exception("HTTP request to JWKS failed for realm '{$realm}' with status {$response->status()}");
                }

                $jwks = $response->json();
                if (! is_array($jwks) || empty($jwks['keys']) || ! is_array($jwks['keys'])) {
                    throw new Exception("Empty or invalid JWKS returned for realm '{$realm}'");
                }

                $parsedKeys = JWK::parseKeySet($jwks, 'RS256');
                $filteredKeys = array_filter($parsedKeys, function (Key $key) use ($allowedAlgorithms) {
                    return in_array($key->getAlgorithm(), $allowedAlgorithms, true);
                });

                if (empty($filteredKeys)) {
                    throw new Exception("No keys found in JWKS matching allowed algorithms for realm '{$realm}'");
                }

                return $filteredKeys;
            } catch (Exception $e) {
                Log::error("Failed to retrieve Keycloak JWKS for realm '{$realm}': ".$e->getMessage(), [
                    'realm' => $realm,
                    'jwks_url' => $jwksUrl,
                ]);

                throw new Exception('Unable to retrieve public keys for token verification');
            }
        });
    }
}
