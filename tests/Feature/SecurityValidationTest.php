<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\UserProfile;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityValidationTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;
    protected string $publicKey;
    protected string $wrongPrivateKey;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Generate test RSA key pair
        $configPath = 'C:/Program Files/Git/mingw64/ssl/openssl.cnf';
        $res = openssl_pkey_new([
            "config" => $configPath,
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($res, $privateKey, null, ["config" => $configPath]);
        $this->privateKey = $privateKey;
        $details = openssl_pkey_get_details($res);
        $this->publicKey = $details["key"];

        // 2. Generate different RSA key pair for testing invalid signature
        $res2 = openssl_pkey_new([
            "config" => $configPath,
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($res2, $wrongPrivateKey, null, ["config" => $configPath]);
        $this->wrongPrivateKey = $wrongPrivateKey;

        // 3. Configure mock settings
        config(['keycloak.mock_mode' => true]);
        config(['keycloak.mock_public_key' => $this->publicKey]);
    }

    /**
     * Helper to generate mock Keycloak JWT token.
     */
    protected function generateToken(array $overrides = [], bool $useWrongKey = false): string
    {
        $payload = array_merge([
            'iss' => 'https://sso.reltroner.com/realms/reltroner-erp',
            'aud' => 'erp-reltroner-be',
            'sub' => (string) Str::uuid(),
            'email' => 'test@example.com',
            'preferred_username' => 'testuser',
            'name' => 'Test User',
            'realm_access' => [
                'roles' => ['erp-user']
            ],
            'exp' => time() + 3600,
            'iat' => time(),
        ], $overrides);

        $key = $useWrongKey ? $this->wrongPrivateKey : $this->privateKey;
        return JWT::encode($payload, $key, 'RS256', 'mock-kid');
    }

    public function test_missing_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/erp/dashboard');
        $response->assertStatus(401);
    }

    public function test_invalid_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer invalid-token'
        ]);
        $response->assertStatus(401);
    }

    public function test_expired_token_returns_401(): void
    {
        $token = $this->generateToken(['exp' => time() - 3600]);
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer ' . $token
        ]);
        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'Unauthorized']);
    }

    public function test_wrong_audience_returns_401(): void
    {
        $token = $this->generateToken(['aud' => 'wrong-audience']);
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer ' . $token
        ]);
        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'Unauthorized']);
    }

    public function test_unknown_realm_returns_401(): void
    {
        $token = $this->generateToken(['iss' => 'https://sso.reltroner.com/realms/wrong-realm']);
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer ' . $token
        ]);
        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'Unauthorized']);
    }

    public function test_invalid_signature_returns_401(): void
    {
        $token = $this->generateToken([], true);
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer ' . $token
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
            'status' => 'active'
        ]);

        $sub = (string) Str::uuid();
        $userProfile = UserProfile::create([
            'keycloak_subject' => $sub,
            'email' => 'user@acme.com',
            'display_name' => 'Acme User',
            'status' => 'active'
        ]);

        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_profile_id' => $userProfile->id,
            'role_key' => 'viewer',
            'status' => 'active'
        ]);

        $token = $this->generateToken([
            'sub' => $sub,
            'email' => 'user@acme.com'
        ]);

        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $tenant->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Acme Corp']);
    }

    public function test_valid_erp_user_token_cannot_access_admin_dashboard(): void
    {
        $sub = (string) Str::uuid();
        $token = $this->generateToken([
            'sub' => $sub,
            'email' => 'user@acme.com'
        ]);

        $response = $this->getJson('/api/v1/admin/dashboard', [
            'Authorization' => 'Bearer ' . $token
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
                'roles' => ['erp-admin']
            ]
        ]);

        $response = $this->getJson('/api/v1/admin/dashboard', [
            'Authorization' => 'Bearer ' . $token
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
            'status' => 'active'
        ]);

        $sub = (string) Str::uuid();
        $token = $this->generateToken([
            'iss' => 'https://sso.reltroner.com/realms/reltroner-admin',
            'sub' => $sub,
            'email' => 'admin@reltroner.com',
            'realm_access' => [
                'roles' => ['erp-admin']
            ]
        ]);

        // Access erp dashboard under tenant, as admin (support access policy bypasses memberships)
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $tenant->id
        ]);

        $response->assertStatus(200);
    }

    public function test_tenant_isolation_non_member_blocked(): void
    {
        $tenantA = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'status' => 'active'
        ]);

        $tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'status' => 'active'
        ]);

        $sub = (string) Str::uuid();
        $userProfile = UserProfile::create([
            'keycloak_subject' => $sub,
            'email' => 'user@tenant-a.com',
            'display_name' => 'Tenant A User',
            'status' => 'active'
        ]);

        TenantMembership::create([
            'tenant_id' => $tenantA->id,
            'user_profile_id' => $userProfile->id,
            'role_key' => 'viewer',
            'status' => 'active'
        ]);

        $token = $this->generateToken([
            'sub' => $sub,
            'email' => 'user@tenant-a.com'
        ]);

        // Attempting to access Tenant B should fail
        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $tenantB->id
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
            'status' => 'suspended'
        ]);

        $sub = (string) Str::uuid();
        $userProfile = UserProfile::create([
            'keycloak_subject' => $sub,
            'email' => 'user@acme.com',
            'display_name' => 'Acme User',
            'status' => 'active'
        ]);

        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_profile_id' => $userProfile->id,
            'role_key' => 'viewer',
            'status' => 'active'
        ]);

        $token = $this->generateToken([
            'sub' => $sub,
            'email' => 'user@acme.com'
        ]);

        $response = $this->getJson('/api/v1/erp/dashboard', [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $tenant->id
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'Tenant account is suspended']);
    }
}
