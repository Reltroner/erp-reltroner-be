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

        $headerJson = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0]));
        $payloadJson = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (!$header || !$payload) {
            throw new Exception('Failed to parse token headers or claims');
        }

        // 2. Validate issuer and resolve realm
        if (empty($payload['iss'])) {
            throw new Exception('Missing issuer claim (iss)');
        }

        $iss = $payload['iss'];
        $realm = $this->extractRealm($iss);
        if (!$realm) {
            throw new Exception('Unknown realm structure in token');
        }

        $allowedRealms = config('keycloak.allowed_realms', []);
        if (!in_array($realm, $allowedRealms)) {
            throw new Exception("Realm '{$realm}' is not allowed");
        }

        // 3. Resolve validation keys
        $keys = [];
        if (config('keycloak.mock_mode')) {
            $mockPublicKey = config('keycloak.mock_public_key');
            if ($mockPublicKey) {
                // If it is a PEM public key, wrap it.
                if (!str_contains($mockPublicKey, '-----BEGIN PUBLIC KEY-----')) {
                    $mockPublicKey = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($mockPublicKey, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
                }
                $keys = ['mock-kid' => new Key($mockPublicKey, 'RS256')];
            } else {
                throw new Exception('Mock mode enabled but no mock public key configured');
            }
        } else {
            $keys = $this->fetchJwksKeys($realm);
        }

        // 4. Decode and verify signature & expiry using Firebase JWT library
        // Apply clock leeway
        $originalLeeway = JWT::$leeway;
        JWT::$leeway = config('keycloak.clock_leeway', 60);

        try {
            $decoded = JWT::decode($token, $keys);
            $decodedArray = json_decode(json_encode($decoded), true);
        } catch (Exception $e) {
            Log::warning('JWT signature or expiry check failed: ' . $e->getMessage());
            throw new Exception('Invalid token signature or expired: ' . $e->getMessage());
        } finally {
            JWT::$leeway = $originalLeeway;
        }

        // 5. Validate audience
        if (empty($decodedArray['aud'])) {
            throw new Exception('Missing audience claim (aud)');
        }

        $expectedAudience = config('keycloak.expected_audience');
        $audiences = is_array($decodedArray['aud']) ? $decodedArray['aud'] : [$decodedArray['aud']];
        if (!in_array($expectedAudience, $audiences)) {
            throw new Exception("Wrong audience '{$expectedAudience}' expected");
        }

        return $decodedArray;
    }

    /**
     * Extract realm name from the iss claim.
     */
    private function extractRealm(string $iss): ?string
    {
        // Example iss: https://sso.reltroner.com/realms/reltroner-erp
        $pattern = '/\/realms\/([a-zA-Z0-9_-]+)/';
        if (preg_match($pattern, $iss, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Fetch JWKS keys for a realm.
     */
    private function fetchJwksKeys(string $realm): array
    {
        $cacheKey = "keycloak_jwks_keys_{$realm}";
        
        return Cache::remember($cacheKey, config('keycloak.jwks_cache_ttl', 3600), function () use ($realm) {
            $baseUrl = config('keycloak.base_url');
            $jwksUrl = "{$baseUrl}/realms/{$realm}/protocol/openid-connect/certs";

            try {
                $response = Http::get($jwksUrl);
                if (!$response->successful()) {
                    throw new Exception("HTTP request to JWKS failed for realm '{$realm}'");
                }
                
                $jwks = $response->json();
                if (!$jwks || empty($jwks['keys'])) {
                    throw new Exception("Empty JWKS returned for realm '{$realm}'");
                }

                return JWK::parseKeySet($jwks);
            } catch (Exception $e) {
                Log::error("Failed to retrieve Keycloak JWKS for realm '{$realm}': " . $e->getMessage());
                throw $e;
            }
        });
    }
}
