<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLoggerService
{
    /**
     * Log an action into audit_logs table.
     */
    public static function log(
        string $action,
        ?string $resourceType = null,
        ?string $resourceId = null,
        array $metadata = []
    ): AuditLog {
        $user = Auth::user();

        return AuditLog::create([
            'business_id' => $user?->business_id,
            'user_id' => $user?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId !== null ? (string) $resourceId : null,
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
