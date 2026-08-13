<?php

use App\Http\Middleware\AuditContextMiddleware;
use App\Http\Middleware\EnsureKeycloakBearerToken;
use App\Http\Middleware\ResolveTenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware([
    EnsureKeycloakBearerToken::class,
    ResolveTenantContext::class,
    AuditContextMiddleware::class,
])->group(function () {

    Route::get('/erp/dashboard', function (Request $request) {
        $tenant = $request->attributes->get('tenant');

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'metrics' => [
                'total_users' => 5,
                'inventory_value' => 12500.00,
                'recent_sales_count' => 42,
            ],
        ]);
    });

    Route::get('/erp/modules', function (Request $request) {
        return response()->json([
            'modules' => [
                'inventory',
                'procurement',
                'sales',
                'finance',
                'reports',
            ],
        ]);
    });

    Route::get('/erp/profile', function (Request $request) {
        $userProfile = $request->attributes->get('user_profile');
        $tenantRole = $request->attributes->get('tenant_role');

        return response()->json([
            'user' => [
                'id' => $userProfile->id,
                'display_name' => $userProfile->display_name,
                'email' => $userProfile->email,
            ],
            'tenant_role' => $tenantRole,
        ]);
    });

    Route::get('/erp/tenants/current', function (Request $request) {
        $tenant = $request->attributes->get('tenant');

        return response()->json([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'plan_key' => $tenant->plan_key,
        ]);
    });

    Route::get('/erp/permissions/me', function (Request $request) {
        $roleKey = $request->attributes->get('tenant_role');

        return response()->json([
            'role' => $roleKey,
            'permissions' => [
                'inventory.products.view',
                'sales.transactions.create',
            ],
        ]);
    });

    Route::get('/erp/usage', function (Request $request) {
        $tenant = $request->attributes->get('tenant');

        return response()->json([
            'tenant_id' => $tenant->id,
            'plan' => $tenant->plan_key,
            'usage' => [
                'users' => ['used' => 3, 'limit' => 10],
                'storage_mb' => ['used' => 150, 'limit' => 1024],
            ],
        ]);
    });
});
