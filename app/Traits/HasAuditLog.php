<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Attach this trait to any Eloquent model to get automatic audit logging
 * on created, updated, and deleted events.
 *
 * Only changed fields are recorded in old_values / new_values.
 * Fields listed in $auditExclude are never logged (e.g. passwords, tokens).
 */
trait HasAuditLog
{
    public static function bootHasAuditLog(): void
    {
        static::created(function ($model) {
            $model->recordAudit('created', [], $model->getAttributes());
        });

        static::updated(function ($model) {
            $dirty    = $model->getDirty();
            $excluded = $model->getAuditExcluded();
            $changed  = array_diff_key($dirty, array_flip($excluded));

            if (empty($changed)) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $changed);
            $model->recordAudit('updated', $old, $changed);
        });

        static::deleted(function ($model) {
            $model->recordAudit('deleted', $model->getOriginal(), []);
        });
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    protected function recordAudit(string $event, array $old, array $new): void
    {
        AuditLog::create([
            'user_id'        => Auth::id(),
            'auditable_type' => static::class,
            'auditable_id'   => $this->getKey(),
            'event'          => $event,
            'old_values'     => empty($old) ? null : $old,
            'new_values'     => empty($new) ? null : $new,
            'url'            => Request::fullUrl(),
            'ip_address'     => Request::ip(),
            'user_agent'     => Request::userAgent(),
            'created_at'     => now(),
        ]);
    }

    /**
     * Fields excluded from audit logging.
     * Override in the model: protected array $auditExclude = ['field'];
     */
    protected function getAuditExcluded(): array
    {
        return array_merge(
            ['updated_at'],
            $this->auditExclude ?? []
        );
    }
}
