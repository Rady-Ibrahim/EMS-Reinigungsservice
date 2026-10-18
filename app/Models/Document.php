<?php

namespace App\Models;

use App\Enums\FileCategoryEnum;
use App\Enums\FileVisibilityEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A document attached to any owner model (FixObject, ExtraAuftrag, …).
 *
 * Visibility follows the same governance as {@see LocationDocument}:
 * `intern` documents are administration-only, `extern` documents are the only
 * ones ever exposed to the mobile API.
 */
class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'uploaded_by',
        'title',
        'notes',
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
            'category' => FileCategoryEnum::class,
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeExtern(Builder $query): Builder
    {
        return $query->where('visibility', FileVisibilityEnum::Extern->value);
    }

    public function scopeIntern(Builder $query): Builder
    {
        return $query->where('visibility', FileVisibilityEnum::Intern->value);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isIntern(): bool
    {
        return $this->visibility === FileVisibilityEnum::Intern;
    }

    public function isExtern(): bool
    {
        return $this->visibility === FileVisibilityEnum::Extern;
    }

    public function sizeInKb(): float
    {
        return round($this->size_bytes / 1024, 2);
    }

    public function displayTitle(): string
    {
        return $this->title
            ?: ($this->original_name ?: __('locations.document.untitled'));
    }
}
