<?php

namespace App\Services;

use App\Enums\AuditEventEnum;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Central, governance-grade audit sink.
 * Captures the actor, the target entity, old/new values, a mandatory reason
 * for governance events, plus full request context (IP, user agent, URL).
 */
class AuditLogger
{
    public function record(
        Model $auditable,
        AuditEventEnum $event,
        array $oldValues = [],
        array $newValues = [],
        ?string $reason = null,
        ?int $actorId = null
    ): AuditLog {
        return AuditLog::create([
            'user_id'        => $actorId ?? Auth::id(),
            'auditable_type' => $auditable::class,
            'auditable_id'   => $auditable->getKey(),
            'event'          => $event->value,
            'old_values'     => empty($oldValues) ? null : $oldValues,
            'new_values'     => empty($newValues) ? null : $newValues,
            'reason'         => $reason,
            'url'            => Request::fullUrl(),
            'ip_address'     => Request::ip(),
            'user_agent'     => Request::userAgent(),
            'created_at'     => now(),
        ]);
    }
}