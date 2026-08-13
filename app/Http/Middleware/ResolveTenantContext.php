<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userProfile = $request->attributes->get('user_profile');
        if (! $userProfile) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'User profile not established',
            ], 401);
        }

        // 1. Resolve tenant ID from header or route parameters
        $tenantId = $request->header('X-Tenant-ID')
            ?: $request->route('tenant')
            ?: $request->input('tenant_id');

        // If no tenant specified, resolve from user's first active membership
        if (! $tenantId) {
            $firstMembership = $userProfile->memberships()
                ->where('status', 'active')
                ->first();

            if (! $firstMembership) {
                // Wait: admin users might not have any membership but can access general ERP features
                if ($userProfile->isAdmin()) {
                    // Admins will resolve the first tenant in the system if any, or they can specify it
                    $firstTenant = Tenant::first();
                    if ($firstTenant) {
                        $tenantId = $firstTenant->id;
                    }
                }

                if (! $tenantId) {
                    return response()->json([
                        'error' => 'Forbidden',
                        'message' => 'No active tenant context available',
                    ], 403);
                }
            } else {
                $tenantId = $firstMembership->tenant_id;
            }
        }

        // 2. Fetch the tenant
        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Tenant not found',
            ], 404);
        }

        // 3. Check if tenant is suspended (blocks normal ERP access)
        if ($tenant->status !== 'active') {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Tenant account is suspended',
            ], 403);
        }

        // 4. Verify membership (admins have general access)
        $isAdmin = $userProfile->isAdmin();
        $membership = $userProfile->memberships()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->first();

        if (! $membership && ! $isAdmin) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'You do not have access to this tenant',
            ], 403);
        }

        $roleKey = $membership ? $membership->role_key : 'super-admin';

        // Set active tenant and user role in request attributes
        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('tenant_role', $roleKey);

        return $next($request);
    }
}
