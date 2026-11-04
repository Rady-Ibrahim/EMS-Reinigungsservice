<?php

namespace App\Models;

use App\Enums\EmployeeAvailabilityStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAvailabilityRequest extends Model
{
    protected $attributes = [
        'status' => 'pending',
    ];

    protected $fillable = [
        'user_id',
        'start_at',
        'end_at',
        'address',
        'notes',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_note',
        'employee_shift_id',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'status' => EmployeeAvailabilityStatusEnum::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(EmployeeShift::class, 'employee_shift_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', EmployeeAvailabilityStatusEnum::Pending);
    }
}
