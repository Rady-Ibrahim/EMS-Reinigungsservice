<?php

namespace App\Models;

use App\Enums\AdminNotificationTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin notification inbox (conflicts, Teamup sync results, system).
 * Created_at only — one-way queue, never updated.
 */
class AdminNotification extends Model
{
    use HasFactory;

    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $fillable = [
        'type',
        'title',
        'message',
        'payload',
        'dedupe_key',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'type'    => AdminNotificationTypeEnum::class,
            'payload' => 'array',
            'is_read' => 'boolean',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeOfType($query, AdminNotificationTypeEnum $type)
    {
        return $query->where('type', $type);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}