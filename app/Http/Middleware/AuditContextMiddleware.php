<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditContextMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userProfile = $request->attributes->get('user_profile');
        $tenant = $request->attributes->get('tenant');
        $roleKey = $request->attributes->get('tenant_role') ?: 'none';

        if ($userProfile) {
            $actorEmail = $userProfile->email ?: 'system';
            $actorUserId = $userProfile->id;

            if ($userProfile->isAdmin()) {
                $roleKey = 'admin:'.($userProfile->adminUser->admin_role ?? 'erp-admin');
            }
        } else {
            $actorEmail = 'anonymous';
            $actorUserId = null;
        }

        $auditContext = [
            'tenant_id' => $tenant ? $tenant->id : null,
            'actor_user_id' => $actorUserId,
            'actor_email' => $actorEmail,
            'actor_role' => $roleKey,
        ];

        $request->attributes->set('audit_context', $auditContext);

        return $next($request);
    }
}
