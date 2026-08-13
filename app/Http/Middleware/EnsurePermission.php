<?php

namespace App\Http\Middleware;

use App\Models\RolePermission;
use App\Models\UserPermissionOverride;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $userProfile = $request->attributes->get('user_profile');
        if (! $userProfile) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'User profile not established',
            ], 401);
        }

        $isAdmin = $userProfile->isAdmin();

        // 1. Check admin specific permission boundaries (starting with 'admin.')
        if (str_starts_with($permission, 'admin.')) {
            if (! $isAdmin) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'Admin role required',
                ], 403);
            }

            $adminUser = $userProfile->adminUser;
            if ($adminUser && $adminUser->admin_role === 'super-admin') {
                return $next($request);
            }

            // Check if erp-admin role has this permission key
            $hasAdminPerm = RolePermission::where('role_key', $adminUser->admin_role)
                ->where('permission_key', $permission)
                ->exists();

            if (! $hasAdminPerm) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => "Required admin permission '{$permission}' not granted",
                ], 403);
            }

            return $next($request);
        }

        // 2. Check ERP domain permission boundaries
        $tenant = $request->attributes->get('tenant');
        $roleKey = $request->attributes->get('tenant_role');

        if (! $tenant || ! $roleKey) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Tenant context or role not resolved',
            ], 403);
        }

        // Admins with support access policy have bypass in ERP tenant APIs
        if ($isAdmin) {
            return $next($request);
        }

        // Check user permission overrides (DENY has higher priority)
        $override = UserPermissionOverride::where('tenant_id', $tenant->id)
            ->where('user_profile_id', $userProfile->id)
            ->where('permission_key', $permission)
            ->first();

        if ($override) {
            if ($override->effect === 'DENY') {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => "Access denied by override for permission '{$permission}'",
                ], 403);
            }
            if ($override->effect === 'ALLOW') {
                return $next($request);
            }
        }

        // Check general role permissions
        $hasRolePerm = RolePermission::where('role_key', $roleKey)
            ->where('permission_key', $permission)
            ->exists();

        if (! $hasRolePerm) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => "Required permission '{$permission}' not granted for role '{$roleKey}'",
            ], 403);
        }

        return $next($request);
    }
}
