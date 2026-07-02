<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RuntimeProtectionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. If it is public health check '/api/v1/system/health', allow it
        if ($request->is('api/v1/system/health')) {
            return $next($request);
        }

        // 2. Otherwise (e.g. /api/v1/system/runtime, /api/v1/system/db-health), check credentials:
        // Either they have a valid admin session/bearer token (EnsureAdminZoneAccess)
        // OR they provide a matching internal token
        $userProfile = $request->attributes->get('user_profile');
        if ($userProfile && $userProfile->isAdmin()) {
            return $next($request);
        }

        $systemToken = env('SYSTEM_INTERNAL_TOKEN');
        $requestToken = $request->header('X-System-Token');

        if ($systemToken && $requestToken && hash_equals($systemToken, $requestToken)) {
            return $next($request);
        }

        return response()->json([
            'error' => 'Forbidden',
            'message' => 'Internal runtime diagnostics access restricted'
        ], 403);
    }
}
