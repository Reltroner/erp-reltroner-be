<?php

namespace App\Domain\Audit;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditLogWriter
{
    /**
     * Write a new audit log record.
     */
    public function log(
        ?string $tenantId,
        ?int $actorUserId,
        string $actorEmail,
        string $actorRole,
        string $entityType,
        string $entityId,
        string $action,
        string $description,
        ?array $before = null,
        ?array $after = null,
        ?array $metadata = null
    ): AuditLog {
        $requestId = Request::header('X-Request-ID');
        $correlationId = Request::header('X-Correlation-ID');
        $ipAddress = Request::ip();
        $userAgent = Request::userAgent();

        $meta = array_merge($metadata ?: [], [
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);

        return AuditLog::create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actorUserId,
            'actor_email_snapshot' => $actorEmail,
            'actor_role_snapshot' => $actorRole,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'description' => $description,
            'before_json' => $before,
            'after_json' => $after,
            'metadata_json' => $meta,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
