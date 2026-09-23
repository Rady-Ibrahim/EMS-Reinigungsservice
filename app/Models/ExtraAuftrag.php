<?php

namespace App\Models;

use App\Enums\AssigneeRoleEnum;
use App\Enums\ExtraAuftragStatusEnum;
use App\Enums\ExtraOrderTypeEnum;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExtraAuftrag extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog;

    protected $table = 'extra_auftraege';

    protected array $auditExclude = ['price', 'internal_cost'];

    protected $fillable = [
        'customer_id',
        'location_id',
        'created_by',
        'title',
        'description',
        'order_type',
        'scheduled_date',
        'scheduled_time_start',
        'estimated_hours',
        'is_travel_time_paid',
        'status',
        'checklist_template',
        'price',
        'internal_cost',
        'internal_notes',
        'cancelled_reason',
        'offline_uuid',
    ];

    protected function casts(): array
    {
        return [
            'order_type'           => ExtraOrderTypeEnum::class,
            'scheduled_date'       => 'date',
            'estimated_hours'      => 'decimal:2',
            'is_travel_time_paid'  => 'boolean',
            'status'               => ExtraAuftragStatusEnum::class,
            'checklist_template'   => 'array',
            // Financial — encrypted at rest
            'price'                => 'encrypted',
            'internal_cost'        => 'encrypted',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(CustomerLocation::class, 'location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(ExtraAuftragAssignee::class);
    }

    public function leader(): HasOne
    {
        return $this->hasOne(ExtraAuftragAssignee::class)
                    ->where('role_in_order', AssigneeRoleEnum::Leader);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ExtraAuftragAssignee::class)
                    ->where('role_in_order', AssigneeRoleEnum::Member);
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'extra_auftrag_assignees')
                    ->withPivot('role_in_order')
                    ->withTimestamps();
    }

    public function travelTracks(): HasMany
    {
        return $this->hasMany(TravelTrack::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(ExtraAuftragExecution::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeForEmployee($query, int $userId)
    {
        return $query->whereHas('assignees', fn($q) => $q->where('user_id', $userId));
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [
            ExtraAuftragStatusEnum::Completed->value,
            ExtraAuftragStatusEnum::Cancelled->value,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function hasLeader(): bool
    {
        return $this->assignees()
                    ->where('role_in_order', AssigneeRoleEnum::Leader)
                    ->exists();
    }

    public function isLeader(int $userId): bool
    {
        return $this->assignees()
                    ->where('user_id', $userId)
                    ->where('role_in_order', AssigneeRoleEnum::Leader)
                    ->exists();
    }

    public function isAssigned(int $userId): bool
    {
        return $this->assignees()->where('user_id', $userId)->exists();
    }

    public function canBeClosed(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // Leader's execution must have after_photos and all checklist items completed
        $leaderExecution = $this->executions()
            ->whereHas('assignee', fn($q) => $q->where('role_in_order', AssigneeRoleEnum::Leader))
            ->first();

        if (! $leaderExecution) {
            return false;
        }

        return $leaderExecution->hasRequiredPhotos()
            && $leaderExecution->isChecklistComplete();
    }

    public function getIs_activeAttribute(): bool
    {
        return ! $this->status->isTerminal();
    }
}
