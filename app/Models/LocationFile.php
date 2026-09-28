<?php

namespace App\Models;

use App\Enums\FileCategoryEnum;
use App\Enums\FileVisibilityEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocationFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'location_id',
        'uploaded_by',
        'title',
        'file_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'visibility',
        'category',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => FileVisibilityEnum::class,
            'category'   => FileCategoryEnum::class,
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function location(): BelongsTo
    {
        return $this->belongsTo(CustomerLocation::class, 'location_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isIntern(): bool
    {
        return $this->visibility === FileVisibilityEnum::Intern;
    }

    public function sizeInKb(): float
    {
        return round($this->size_bytes / 1024, 2);
    }

    /**
     * Human readable label. `title` is the client-facing name; older rows may
     * only carry the uploaded `original_name`.
     */
    public function displayTitle(): string
    {
        return $this->title ?: ($this->original_name ?: __('locations.document.untitled'));
    }
}
