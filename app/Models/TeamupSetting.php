<?php

namespace App\Models;

use App\Enums\CalendarEventTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Teamup integration credentials + preferences (single row).
 */
class TeamupSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_key',
        'api_key',
        'timezone',
        'subcalendar_ids',
        'enabled',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'calendar_key'    => 'encrypted',
            'api_key'         => 'encrypted',
            'subcalendar_ids' => 'array',
            'enabled'         => 'boolean',
            'last_synced_at'  => 'datetime',
        ];
    }

    /**
     * Retrieve the active settings row (singleton) — never null.
     */
    public static function current(): static
    {
        return static::first() ?? new static();
    }

    public function isConfigured(): bool
    {
        return $this->enabled
            && ! empty($this->calendar_key)
            && ! empty($this->api_key)
            && $this->subcalendarFor('default') !== null;
    }

    public function subcalendarFor(string $eventType): ?int
    {
        $map  = $this->subcalendar_ids ?? [];
        $value = $map[$eventType] ?? $map['default'] ?? null;

        return $value !== null ? (int) $value : null;
    }
}