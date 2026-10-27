<?php

namespace App\Services;

use App\Enums\FileCategoryEnum;
use App\Enums\FileVisibilityEnum;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Generic, polymorphic document store shared by Fixobjekte and Extra-Aufträge.
 *
 * The visibility flag is the security boundary: `intern` documents are
 * administration-only while `extern` documents are the only ones ever handed
 * to field staff through the mobile API. The service never exposes a storage
 * path — files live on the private disk and are streamed through
 * {@see download()}.
 */
class DocumentService
{
    /** Documents larger than this are rejected outright. */
    public const MAX_KILOBYTES = 20480; // 20 MB

    public const DISK = 'local';

    public const ALLOWED_MIME = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/csv',
    ];

    // ── Read ───────────────────────────────────────────────────────────────

    /**
     * List the documents of an owner, honouring the caller's clearance.
     *
     * @return Collection<int, Document>
     */
    public function listFor(Model $owner, User $actor)
    {
        return $this->visibleQuery($actor)
            ->where('documentable_type', $owner->getMorphClass())
            ->where('documentable_id', $owner->getKey())
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Only the externally visible documents of an owner (mobile API).
     *
     * @return Collection<int, Document>
     */
    public function listExtern(Model $owner)
    {
        return $owner->documents()
            ->where('visibility', FileVisibilityEnum::Extern->value)
            ->orderByDesc('created_at')
            ->get();
    }

    // ── Write ──────────────────────────────────────────────────────────────

    public function store(
        Model $owner,
        User $actor,
        UploadedFile $file,
        ?string $title = null,
        string|FileVisibilityEnum|null $visibility = null,
        string|FileCategoryEnum|null $category = null,
        ?string $notes = null
    ): Document {
        $visibility = $visibility instanceof FileVisibilityEnum
            ? $visibility
            : (FileVisibilityEnum::tryFrom((string) $visibility) ?? FileVisibilityEnum::Intern);

        $category = $category instanceof FileCategoryEnum
            ? $category
            : (FileCategoryEnum::tryFrom((string) $category) ?? FileCategoryEnum::Other);

        $originalName = $file->getClientOriginalName();

        $path = $file->store($this->directory($owner), 'local');

        return Document::create([
            'documentable_type' => $owner->getMorphClass(),
            'documentable_id' => $owner->getKey(),
            'uploaded_by' => $actor->id,
            'title' => $title ?: pathinfo($originalName, PATHINFO_FILENAME),
            'notes' => $notes,
            'file_path' => $path,
            'original_name' => $originalName,
            'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
            'size_bytes' => (int) $file->getSize(),
            'visibility' => $visibility->value,
            'category' => $category->value,
        ]);
    }

    public function delete(Document $document): void
    {
        if ($document->file_path && Storage::disk(self::DISK)->exists($document->file_path)) {
            Storage::disk(self::DISK)->delete($document->file_path);
        }

        $document->delete();
    }

    // ── Download ───────────────────────────────────────────────────────────

    /**
     * @return array{path: string, name: string, mime: string}
     */
    public function download(Document $document): array
    {
        if (! Storage::disk(self::DISK)->exists($document->file_path)) {
            throw ValidationException::withMessages([
                'document' => __('locations.document.file_missing'),
            ]);
        }

        return [
            'path' => Storage::disk(self::DISK)->path($document->file_path),
            'name' => $this->downloadName($document),
            'mime' => $document->mime_type ?: 'application/octet-stream',
        ];
    }

    /**
     * Resolve a document that belongs to the given owner and is visible to the
     * actor. Returns null when it is missing, foreign or intern-hidden.
     */
    public function findVisible(Model $owner, int $documentId, User $actor): ?Document
    {
        return $this->visibleQuery($actor)
            ->where('documentable_type', $owner->getMorphClass())
            ->where('documentable_id', $owner->getKey())
            ->whereKey($documentId)
            ->first();
    }

    // ── Validation ─────────────────────────────────────────────────────────

    /**
     * @return array<string, array<int, string>>
     */
    public static function uploadRules(string $prefix = 'document'): array
    {
        return [
            $prefix => ['nullable', 'file', 'max:'.self::MAX_KILOBYTES, 'mimetypes:'.implode(',', self::ALLOWED_MIME)],
            $prefix.'_title' => ['nullable', 'string', 'max:191'],
            $prefix.'_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    // ── Private ────────────────────────────────────────────────────────────

    private function visibleQuery(User $actor)
    {
        $query = Document::query();

        if (! $actor->isAdministrator()) {
            $query->where('visibility', FileVisibilityEnum::Extern->value);
        }

        return $query;
    }

    private function directory(Model $owner): string
    {
        return 'documents/'.Str::snake(class_basename($owner)).'/'.$owner->getKey();
    }

    private function downloadName(Document $document): string
    {
        $extension = pathinfo((string) $document->original_name, PATHINFO_EXTENSION);
        $base = Str::slug($document->displayTitle()) ?: 'dokument';

        return $extension ? "{$base}.{$extension}" : $base;
    }
}
