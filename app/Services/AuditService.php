<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an action to the audit_logs table.
     *
     * @param  string       $action      e.g. 'inspection.submit'
     * @param  string       $module      e.g. 'inspection'
     * @param  string|null  $targetId    UUID of the affected record
     * @param  string|null  $targetType  Model class name
     * @param  array        $payload     Extra context (before/after, etc.)
     */
    public static function log(
        string $action,
        string $module,
        ?string $targetId = null,
        ?string $targetType = null,
        array $payload = []
    ): void {
        AuditLog::create([
            'user_id'     => Auth::id(),
            'action'      => $action,
            'module'      => $module,
            'target_id'   => $targetId,
            'target_type' => $targetType,
            'payload'     => $payload,
            'ip_address'  => Request::ip(),
            'created_at'  => now(),
        ]);
    }
}