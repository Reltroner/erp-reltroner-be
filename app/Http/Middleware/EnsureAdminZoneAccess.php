<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminZoneAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userProfile = $request->attributes->get('user_profile');
        $tokenPayload = $request->attributes->get('token_payload');

        if (!$userProfile || !$tokenPayload) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'User credentials not established'
            ], 401);
        }

        // 1. Check roles from Keycloak token
        $roles = $tokenPayload['realm_access']['roles'] ?? [];
        $hasTokenRole = in_array('erp-admin', $roles) || in_array('super-admin', $roles);

        // 2. Check local database admin registry
        $adminRegistry = AdminUser::where('user_profile_id', $userProfile->id)->first();

        // If Keycloak role is present, auto-sync/register in db if not exists
        if ($hasTokenRole && !$adminRegistry) {
            $adminRegistry = AdminUser::create([
                'user_profile_id' => $userProfile->id,
                'admin_role' => in_array('super-admin', $roles) ? 'super-admin' : 'erp-admin',
                'status' => 'active',
                'granted_at' => now(),
            ]);
        }

        // 3. User must have the role in token and not be suspended locally, OR be active locally
        $isLocalActive = $adminRegistry && $adminRegistry->status === 'active';

        if (!$hasTokenRole && !$isLocalActive) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Admin zone access required'
            ], 403);
        }

        if ($adminRegistry && $adminRegistry->status !== 'active') {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Admin account is suspended'
            ], 403);
        }

        return $next($request);
    }
}
