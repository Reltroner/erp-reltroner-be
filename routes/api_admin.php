<?php

use App\Http\Middleware\AuditContextMiddleware;
use App\Http\Middleware\EnsureAdminZoneAccess;
use App\Http\Middleware\EnsureKeycloakBearerToken;
use App\Http\Middleware\EnsurePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware([
    EnsureKeycloakBearerToken::class,
    EnsureAdminZoneAccess::class,
    AuditContextMiddleware::class
])->group(function () {

    Route::get('/admin/dashboard', function () {
        return response()->json([
            'admin_metrics' => [
                'total_tenants' => 12,
                'active_subscriptions' => 8,
                'system_status' => 'healthy'
            ]
        ]);
    });

    Route::get('/admin/tenants', function () {
        return response()->json([
            'tenants' => \App\Models\Tenant::all()
        ]);
    })->middleware(EnsurePermission::class . ':admin.tenants.view');

    Route::get('/admin/tenants/{id}', function ($id) {
        $tenant = \App\Models\Tenant::find($id);
        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }
        return response()->json($tenant);
    })->middleware(EnsurePermission::class . ':admin.tenants.view');

    Route::get('/admin/users', function () {
        return response()->json([
            'users' => \App\Models\UserProfile::all()
        ]);
    })->middleware(EnsurePermission::class . ':admin.users.view');

    Route::get('/admin/users/{id}', function ($id) {
        $user = \App\Models\UserProfile::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        return response()->json($user);
    })->middleware(EnsurePermission::class . ':admin.users.view');

    Route::get('/admin/audit-logs', function () {
        return response()->json([
            'audit_logs' => \App\Models\AuditLog::latest()->take(100)->get()
        ]);
    })->middleware(EnsurePermission::class . ':admin.audit.view');

    Route::get('/admin/system/health', function () {
        return response()->json([
            'status' => 'healthy',
            'checks' => [
                'database' => 'OK',
                'cache' => 'OK',
                'keycloak' => 'OK'
            ]
        ]);
    });

    Route::get('/admin/entitlements', function () {
        return response()->json([
            'entitlements' => \App\Models\FeatureEntitlement::all()
        ]);
    })->middleware(EnsurePermission::class . ':admin.entitlements.view');

    Route::patch('/admin/entitlements/{id}', function (Request $request, $id) {
        $entitlement = \App\Models\FeatureEntitlement::find($id);
        if (!$entitlement) {
            return response()->json(['error' => 'Entitlement not found'], 404);
        }
        
        $request->validate([
            'is_enabled' => 'required|boolean'
        ]);

        $before = $entitlement->toArray();
        $entitlement->update(['is_enabled' => $request->input('is_enabled')]);
        $after = $entitlement->toArray();

        // Write Audit Log
        $auditContext = $request->attributes->get('audit_context');
        app(\App\Domain\Audit\AuditLogWriter::class)->log(
            $auditContext['tenant_id'],
            $auditContext['actor_user_id'],
            $auditContext['actor_email'],
            $auditContext['actor_role'],
            'FeatureEntitlement',
            (string) $id,
            'update',
            'Updated feature entitlement status',
            $before,
            $after
        );

        return response()->json($entitlement);
    })->middleware(EnsurePermission::class . ':admin.entitlements.update');

    Route::get('/admin/usage/tenants/{tenantId}', function ($tenantId) {
        return response()->json([
            'tenant_id' => $tenantId,
            'usage' => \App\Models\UsageCounter::where('tenant_id', $tenantId)->get()
        ]);
    });

    Route::get('/admin/plans', function () {
        return response()->json([
            'plans' => \App\Models\Plan::all()
        ]);
    });

    Route::get('/admin/features', function () {
        return response()->json([
            'features' => \App\Models\FeatureEntitlement::select('feature_key')->distinct()->get()
        ]);
    });
});
