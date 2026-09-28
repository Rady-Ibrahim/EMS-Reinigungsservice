<?php

namespace App\Models;

use App\Enums\ComplaintStatusEnum;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A customer complaint (Reklamation) about a specific location.
 *
 * Complaints are the company's quality signal: they feed the complaint KPI on
 * the dashboard and the "open complaints" smart alert. Every status change is
 * captured in the audit log via {@see HasAuditLog}.
 */
class Complaint extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog;

    protected $fillable = [
        'customer_id',
        'location_id',
        'assigned_employee_id',
        'reported_by',
        'occurred_at',
        'title',
        'description',
        'photos',
        'status',
        'resolution',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'status'     => ComplaintStatusEnum::class,
            'photos'     => 'array',
            'occurred_at'=> 'datetime',
            'resolved_at'=> 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    /**
     * `client_id` is the field name used by the mobile API specification; the
     * column is called `customer_id` throughout the schema. Both spellings
     * read and write the same stored value.
     */
    protected function clientId(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->customer_id,
            set: fn($value) => ['customer_id' => $value],
        );
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(CustomerLocation::class, 'location_id');
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_employee_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ComplaintStatusEnum::Pending->value,
            ComplaintStatusEnum::InProgress->value,
        ]);
    }

    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('created_at', $year)->whereMonth('created_at', $month);
    }

    /** Only the complaints the given employee is responsible for. */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_employee_id', $userId);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function hasPhotos(): bool
    {
        return ! empty($this->photos);
    }

    /**
     * How long the complaint has been open, in whole days.
     */
    public function ageInDays(): int
    {
        return (int) $this->created_at->startOfDay()->diffInDays(now()->startOfDay());
    }
}
