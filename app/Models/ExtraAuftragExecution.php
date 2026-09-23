<?php

namespace App\Models;

use App\Enums\AssigneeRoleEnum;
use App\Enums\ExtraExecutionStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtraAuftragExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'extra_auftrag_id',
        'user_id',
        'work_start',
        'work_end',
        'work_minutes',
        'paid_minutes',
        'gps_work_start_lat',
        'gps_work_start_lng',
        'gps_work_end_lat',
        'gps_work_end_lng',
        'status',
        'before_photos',
        'after_photos',
        'checklist_items',
        'employee_notes',
        'offline_uuid',
        'client_submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'work_start'          => 'datetime',
            'work_end'            => 'datetime',
            'gps_work_start_lat'  => 'decimal:8',
            'gps_work_start_lng'  => 'decimal:8',
            'gps_work_end_lat'    => 'decimal:8',
            'gps_work_end_lng'    => 'decimal:8',
            'status'              => ExtraExecutionStatusEnum::class,
            'before_photos'       => 'array',
            'after_photos'        => 'array',
            'checklist_items'     => 'array',
            'client_submitted_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function extraAuftrag(): BelongsTo
    {
        return $this->belongsTo(ExtraAuftrag::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(ExtraAuftragAssignee::class, 'user_id', 'user_id')
                    ->where('extra_auftrag_id', $this->extra_auftrag_id);
    }

    public function travelTrack(): BelongsTo
    {
        return $this->belongsTo(TravelTrack::class, 'user_id', 'user_id')
                    ->where('extra_auftrag_id', $this->extra_auftrag_id);
    }

    // ── Validation helpers ─────────────────────────────────────────────────

    /**
     * Leader must have both before and after photos before closing.
     */
    public function hasRequiredPhotos(): bool
    {
        return ! empty($this->before_photos) && ! empty($this->after_photos);
    }

    /**
     * All checklist items must be marked completed.
     */
    public function isChecklistComplete(): bool
    {
        if (empty($this->checklist_items)) {
            return true; // no checklist = no requirement
        }

        foreach ($this->checklist_items as $item) {
            if (empty($item['completed'])) {
                return false;
            }
        }

        return true;
    }

    // ── Workflow ───────────────────────────────────────────────────────────

    /**
     * Transition to next status.
     * Leader-only states are enforced by the caller (service layer).
     */
    public function transitionTo(ExtraExecutionStatusEnum $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw new \LogicException(
                "Cannot transition from [{$this->status->value}] to [{$next->value}]."
            );
        }
        $this->update(['status' => $next]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isCompleted(): bool
    {
        return $this->status === ExtraExecutionStatusEnum::Completed;
    }

    public function workDurationMinutes(): ?int
    {
        if (! $this->work_start || ! $this->work_end) {
            return null;
        }
        return (int) $this->work_start->diffInMinutes($this->work_end);
    }
}
