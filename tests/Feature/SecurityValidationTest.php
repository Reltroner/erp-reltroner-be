<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\UserProfile;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityValidationTest extends TestCase
{
    use RefreshDatabase;

    private const WINDOWS_OPENSSL_CONFIG = 'C:/Program Files/Git/mingw64/ssl/openssl.cnf';

    /** @var array{private: string, public: string}|null */
    protected static ?array $cachedKeyPair = null;

    /** @var array{private: string, public: string}|null */
    protected static ?array $cachedWrongKeyPair = null;

    protected string $privateKey;

    protected string $publicKey;

    protected string $wrongPrivateKey;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Generate test RSA key pair (cached statically across tests)
        $keyPair = self::$cachedKeyPair ??= $this->generateRsaKeyPair();
        $this->privateKey = $keyPair['private'];
        $this->publicKey = $keyPair['public'];

        // 2. Generate different RSA key pair for testing invalid signature
        $wrongKeyPair = self::$cachedWrongKeyPair ??= $this->generateRsaKeyPair();
        $this->wrongPrivateKey = $wrongKeyPair['private'];

        // 3. Configure mock settings
        config(['keycloak.mock_mode' => true]);
        config(['keycloak.mock_public_key' => $this->publicKey]);
    }

    /**
     * @return array{private: string, public: string}
     */
    private function generateRsaKeyPair(): array
    {
        $options = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $this->drainOpenSslErrors();
        $key = openssl_pkey_new($options);
        $generationErrors = $key === false ? $this->drainOpenSslErrors() : [];
        $configPath = null;

        if ($key === false && PHP_OS_FAMILY === 'Windows') {
            $resolvedConfigPath = realpath(self::WINDOWS_OPENSSL_CONFIG);

            if (
                $resolvedConfigPath !== false
                && is_file($resolvedConfigPath)
                && is_readable($resolvedConfigPath)
            ) {
                $configPath = $resolvedConfigPath;
                $this->drainOpenSslErrors();
                $key = openssl_pkey_new([
                    ...$options,
                    'config' => $configPath,
                ]);

                if ($key === false) {
                    $generationErrors = array_merge(
                        $generationErrors,
                        $this->drainOpenSslErrors(),
                    );
                }
            }
        }

        if ($key === false) {
            self::fail($this->openSslFailureMessage(
                'RSA test key generation failed',
                $generationErrors,
            ));
        }

        $privateKey = '';
        $this->drainOpenSslErrors();
        $exported = $configPath === null
            ? openssl_pkey_export($key, $privateKey)
            : openssl_pkey_export($key, $privateKey, null, ['config' => $configPath]);
        $exportErrors = $this->drainOpenSslErrors();

        if ($exported !== true || $privateKey === '') {
            self::fail($this->openSslFailureMessage(
                'RSA test private-key export failed',
                $exportErrors,
            ));
        }

        $this->drainOpenSslErrors();
        $details = openssl_pkey_get_details($key);
        $detailsErrors = $this->drainOpenSslErrors();

        if (
            $details === false
            || ! array_key_exists('key', $details)
            || ! is_string($details['key'])
            || $details['key'] === ''
        ) {
            self::fail($this->openSslFailureMessage(
                'RSA test public-key extraction failed',
                $detailsErrors,
            ));
        }

        return [
            'private' => $privateKey,
            'public' => $details['key'],
        ];
    }

    /**
     * @return list<string>
     */
    private function drainOpenSslErrors(): array
    {
        $errors = [];

        while (($error = openssl_error_string()) !== false) {
            $errors[] = $error;
        }

        return $errors;
    }

    /**
     * @param  list<string>  $errors
     */
    private function openSslFailureMessage(string $message, array $errors): string
    {
        $details = $errors === []
            ? 'No OpenSSL error details were reported.'
            : implode(' | ', array_values(array_unique($errors)));

        return $message.'. '.$details;
    }

    /**
     * Helper to generate mock Keycloak JWT token.
     */
    protected function generateToken(array $overrides = [], bool $useWrongKey = false, string $alg = 'RS256'): string
    {
        $payload = array_merge([
            'iss' => 'https://sso.reltroner.com/realms/reltroner-erp',
            'aud' => 'erp-reltroner-be',
            'sub' => (string) Str::uuid(),
            'email' => 'test@example.com',
            'preferred_username' => 'testuser',
            'name' => 'Test User',
            'realm_access' => [
                'roles' => ['erp-user'],
            ],
            'exp' => time() + 3600,
            'iat' => time(),
        ], $overrides);

        $key = $useWrongKey ? $this->wrongPrivateKey : $this->privateKey;

        return JWT::encode($payload, $key, $alg, 'mock-kid');
    }

    public function test_missing_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/erp/dashboard');
        $response->assertStatus(401);
    }

    public function test_invalid_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer invalid-token',
        ]);
        $response->assertStatus(401);
    }

    public function test_expired_token_returns_401(): void
    {
        $token = $this->generateToken(['exp' => time() - 3600]);
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer '.$token,
        ]);
        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'Unauthorized']);
    }

    public function test_wrong_audience_returns_401(): void
    {
        $token = $this->generateToken(['aud' => 'wrong-audience']);
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer '.$token,
        ]);
        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'Unauthorized']);
    }

    public function test_unknown_realm_returns_401(): void
    {
        $token = $this->generateToken(['iss' => 'https://sso.reltroner.com/realms/wrong-realm']);
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer '.$token,
        ]);
        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'Unauthorized']);
    }

    public function test_invalid_signature_returns_401(): void
    {
        $token = $this->generateToken([], true);
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer '.$token,
        ]);
        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'Unauthorized']);
    }

    public function test_valid_erp_user_token_can_access_erp_dashboard(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Acme Corp',
            'slug' => 'acme',
            'status' => 'active',
        ]);

        $sub = (string) Str::uuid();
        $userProfile = UserProfile::create([
            'keycloak_subject' => $sub,
            'email' => 'user@acme.com',
            'display_name' => 'Acme User',
            'status' => 'active',
        ]);

        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_profile_id' => $userProfile->id,
            'role_key' => 'viewer',
            'status' => 'active',
        ]);

        $token = $this->generateToken([
            'sub' => $sub,
            'email' => 'user@acme.com',
        ]);

        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-ID' => $tenant->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Acme Corp']);
    }

    public function test_valid_erp_user_token_cannot_access_admin_dashboard(): void
    {
        $sub = (string) Str::uuid();
        $token = $this->generateToken([
            'sub' => $sub,
            'email' => 'user@acme.com',
        ]);

        $response = $this->getJson('/api/v1/admin/dashboard', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(403);
    }

    public function test_valid_admin_token_can_access_admin_dashboard(): void
    {
        $sub = (string) Str::uuid();
        $token = $this->generateToken([
            'iss' => 'https://sso.reltroner.com/realms/reltroner-admin',
            'sub' => $sub,
            'email' => 'admin@reltroner.com',
            'realm_access' => [
                'roles' => ['erp-admin'],
            ],
        ]);

        $response = $this->getJson('/api/v1/admin/dashboard', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['admin_metrics']);
    }

    public function test_valid_admin_token_can_access_erp_dashboard(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Acme Corp',
            'slug' => 'acme',
            'status' => 'active',
        ]);

        $sub = (string) Str::uuid();
        $token = $this->generateToken([
            'iss' => 'https://sso.reltroner.com/realms/reltroner-admin',
            'sub' => $sub,
            'email' => 'admin@reltroner.com',
            'realm_access' => [
                'roles' => ['erp-admin'],
            ],
        ]);

        // Access erp dashboard under tenant, as admin (support access policy bypasses memberships)
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-ID' => $tenant->id,
        ]);

        $response->assertStatus(200);
    }

    public function test_tenant_isolation_non_member_blocked(): void
    {
        $tenantA = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'status' => 'active',
        ]);

        $tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'status' => 'active',
        ]);

        $sub = (string) Str::uuid();
        $userProfile = UserProfile::create([
            'keycloak_subject' => $sub,
            'email' => 'user@tenant-a.com',
            'display_name' => 'Tenant A User',
            'status' => 'active',
        ]);

        TenantMembership::create([
            'tenant_id' => $tenantA->id,
            'user_profile_id' => $userProfile->id,
            'role_key' => 'viewer',
            'status' => 'active',
        ]);

        $token = $this->generateToken([
            'sub' => $sub,
            'email' => 'user@tenant-a.com',
        ]);

        // Attempting to access Tenant B should fail
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-ID' => $tenantB->id,
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'You do not have access to this tenant']);
    }

    public function test_suspended_tenant_blocks_erp_access(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Acme Corp',
            'slug' => 'acme',
            'status' => 'suspended',
        ]);

        $sub = (string) Str::uuid();
        $userProfile = UserProfile::create([
            'keycloak_subject' => $sub,
            'email' => 'user@acme.com',
            'display_name' => 'Acme User',
            'status' => 'active',
        ]);

        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_profile_id' => $userProfile->id,
            'role_key' => 'viewer',
            'status' => 'active',
        ]);

        $token = $this->generateToken([
            'sub' => $sub,
            'email' => 'user@acme.com',
        ]);

        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-ID' => $tenant->id,
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'Tenant account is suspended']);
    }

    public function test_wrong_issuer_host_with_valid_realm_returns_401(): void
    {
        $token = $this->generateToken([
            'iss' => 'https://evil.example/realms/reltroner-erp',
        ]);
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer '.$token,
        ]);
        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'Unauthorized']);
    }

    public function test_wrong_issuer_scheme_returns_401(): void
    {
        $token = $this->generateToken([
            'iss' => 'http://sso.reltroner.com/realms/reltroner-erp',
        ]);
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer '.$token,
        ]);
        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'Unauthorized']);
    }

    public function test_wrong_issuer_path_returns_401(): void
    {
        $token = $this->generateToken([
            'iss' => 'https://sso.reltroner.com/auth/realms/reltroner-erp',
        ]);
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer '.$token,
        ]);
        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'Unauthorized']);
    }

    public function test_issuer_with_trailing_slash_returns_401(): void
    {
        $token = $this->generateToken([
            'iss' => 'https://sso.reltroner.com/realms/reltroner-erp/',
        ]);
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer '.$token,
        ]);
        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'Unauthorized']);
    }

    public function test_disallowed_algorithm_hs256_returns_401(): void
    {
        $payload = [
            'iss' => 'https://sso.reltroner.com/realms/reltroner-erp',
            'aud' => 'erp-reltroner-be',
            'sub' => (string) Str::uuid(),
            'email' => 'test@example.com',
            'preferred_username' => 'testuser',
            'name' => 'Test User',
            'realm_access' => ['roles' => ['erp-user']],
            'exp' => time() + 3600,
            'iat' => time(),
        ];
        $token = JWT::encode($payload, 'disallowed-hmac-secret-key-32bytes!', 'HS256');

        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer '.$token,
        ]);
        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'Unauthorized']);
    }

    public function test_algorithm_outside_configured_allow_list_returns_401(): void
    {
        // Allowed algorithm is configured as RS256, but token uses RS512
        config(['keycloak.allowed_algorithms' => ['RS256']]);

        $token = $this->generateToken([], false, 'RS512');

        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer '.$token,
        ]);
        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'Unauthorized']);
    }

    public function test_missing_alg_header_returns_401(): void
    {
        $header = ['typ' => 'JWT'];
        $payload = [
            'iss' => 'https://sso.reltroner.com/realms/reltroner-erp',
            'aud' => 'erp-reltroner-be',
            'sub' => (string) Str::uuid(),
            'email' => 'test@example.com',
            'preferred_username' => 'testuser',
            'name' => 'Test User',
            'realm_access' => ['roles' => ['erp-user']],
            'exp' => time() + 3600,
            'iat' => time(),
        ];

        $encodedHeader = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $encodedPayload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode('dummy-signature'), '+/', '-_'), '=');
        $token = "{$encodedHeader}.{$encodedPayload}.{$signature}";

        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer '.$token,
        ]);
        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'Unauthorized']);
    }

    public function test_jwks_http_failure_returns_401_without_leaking_internals(): void
    {
        config(['keycloak.mock_mode' => false]);
        Http::fake([
            'https://sso.reltroner.com/realms/reltroner-erp/protocol/openid-connect/certs' => Http::response('Internal Server Error', 500),
        ]);

        $token = $this->generateToken();
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer '.$token,
        ]);
        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'Unauthorized']);
        $this->assertStringNotContainsString('500', $response->json('message') ?? '');
        $this->assertStringNotContainsString('openid-connect', $response->json('message') ?? '');
    }
}
