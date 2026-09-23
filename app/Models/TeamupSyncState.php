<?php

namespace App\Models;

use App\Enums\TeamupSyncStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamupSyncState extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'teamup_event_id',
        'remote_hash',
        'status',
        'error',
        'last_pushed_at',
        'last_pulled_at',
    ];

    protected function casts(): array
    {
        return [
            'status'         => TeamupSyncStatusEnum::class,
            'last_pushed_at' => 'datetime',
            'last_pulled_at' => 'datetime',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeForEntity($query, string $entityType, int $entityId)
    {
        return $query->where('entity_type', $entityType)->where('entity_id', $entityId);
    }

    public function scopePending($query)
    {
        return $query->where('status', TeamupSyncStatusEnum::Pending);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === TeamupSyncStatusEnum::Pending;
    }
}