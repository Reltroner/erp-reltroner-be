<?php

return [
    'base_url' => env('KEYCLOAK_BASE_URL', 'https://sso.reltroner.com'),
    'allowed_realms' => explode(',', env('KEYCLOAK_ALLOWED_REALMS', 'reltroner-erp,reltroner-admin')),
    'expected_audience' => env('KEYCLOAK_EXPECTED_AUDIENCE', 'erp-reltroner-be'),
    'allowed_algorithms' => explode(',', env('KEYCLOAK_ALLOWED_ALGORITHMS', 'RS256')),
    'jwks_cache_ttl' => (int) env('KEYCLOAK_JWKS_CACHE_TTL_SECONDS', 3600),
    'clock_leeway' => (int) env('KEYCLOAK_CLOCK_LEEWAY_SECONDS', 60),
    
    // For local tests to bypass network fetching
    'mock_mode' => env('KEYCLOAK_MOCK_MODE', false) || env('APP_ENV') === 'testing',
    'mock_public_key' => env('KEYCLOAK_MOCK_PUBLIC_KEY'),
];
