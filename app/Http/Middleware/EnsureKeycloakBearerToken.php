<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use App\Models\UserProfile;
use App\Security\Keycloak\KeycloakTokenValidator;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKeycloakBearerToken
{
    protected KeycloakTokenValidator $validator;

    public function __construct(KeycloakTokenValidator $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = $request->header('Authorization');
        if (! $authorization || ! str_starts_with($authorization, 'Bearer ')) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Bearer token is missing or invalid',
            ], 401);
        }

        $token = substr($authorization, 7);

        try {
            $payload = $this->validator->validateToken($token);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => $e->getMessage(),
            ], 401);
        }

        $sub = $payload['sub'] ?? null;
        $email = $payload['email'] ?? null;
        $displayName = $payload['name'] ?? $payload['preferred_username'] ?? $email ?? 'Unknown User';

        if (! $sub) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Token has no sub claim',
            ], 401);
        }

        // Find or create UserProfile
        $userProfile = UserProfile::firstOrCreate(
            ['keycloak_subject' => $sub],
            [
                'email' => $email,
                'display_name' => $displayName,
                'status' => 'active',
            ]
        );

        // Sync display name or email if updated in Keycloak
        if ($userProfile->email !== $email || $userProfile->display_name !== $displayName) {
            $userProfile->update([
                'email' => $email,
                'display_name' => $displayName,
            ]);
            // Fire UserProfileSynced event
            event('UserProfileSynced', $userProfile);
        }

        // Sync admin status if role exists in token
        $roles = $payload['realm_access']['roles'] ?? [];
        $hasAdminRole = in_array('erp-admin', $roles) || in_array('super-admin', $roles);

        if ($hasAdminRole) {
            AdminUser::updateOrCreate(
                ['user_profile_id' => $userProfile->id],
                [
                    'admin_role' => in_array('super-admin', $roles) ? 'super-admin' : 'erp-admin',
                    'status' => 'active',
                    'granted_at' => $userProfile->adminUser->granted_at ?? now(),
                ]
            );
        }

        if ($userProfile->status !== 'active') {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'User profile is suspended',
            ], 403);
        }

        // Attach properties to the request attributes
        $request->attributes->set('user_profile', $userProfile);
        $request->attributes->set('token_payload', $payload);

        return $next($request);
    }
}
