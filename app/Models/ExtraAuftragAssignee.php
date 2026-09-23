<?php

namespace App\Models;

use App\Enums\AssigneeRoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExtraAuftragAssignee extends Model
{
    use HasFactory;

    protected $fillable = [
        'extra_auftrag_id',
        'user_id',
        'role_in_order',
    ];

    protected function casts(): array
    {
        return [
            'role_in_order' => AssigneeRoleEnum::class,
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function extraAuftrag(): BelongsTo
    {
        return $this->belongsTo(ExtraAuftrag::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function execution(): HasOne
    {
        return $this->hasOne(ExtraAuftragExecution::class, 'user_id', 'user_id')
                    ->where('extra_auftrag_id', $this->extra_auftrag_id);
    }

    public function travelTrack(): HasOne
    {
        return $this->hasOne(TravelTrack::class, 'user_id', 'user_id')
                    ->where('extra_auftrag_id', $this->extra_auftrag_id);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isLeader(): bool
    {
        return $this->role_in_order === AssigneeRoleEnum::Leader;
    }
}
