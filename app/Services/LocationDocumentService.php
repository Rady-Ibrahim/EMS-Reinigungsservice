<?php

namespace App\Services;

use App\Enums\FileCategoryEnum;
use App\Enums\FileVisibilityEnum;
use App\Models\CustomerLocation;
use App\Models\LocationDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Management of the documents attached to a customer location.
 *
 * Visibility is the security boundary of this module:
 *   - `intern` documents are administration-only. They are never listed and
 *     never downloadable for field staff.
 *   - `extern` documents may be listed and downloaded by Vorarbeiter and
 *     Mitarbeiter through the mobile API.
 *
 * Files live on the private disk and are only ever served through
 * {@see download()}, never by exposing a path to the client.
 *
 * IMPORTANT: the boundary is enforced by {@see visibleQuery()} — not by
 * {@see download()} itself, which trusts its argument. Callers holding a
 * bare `LocationDocument` must first pass it through {@see findVisible()}.
 * The admin web routes rely on the `role:administrator` middleware for this
 * instead, since administrators may see intern documents anyway.
 */
class LocationDocumentService
{
    /** Documents larger than this are rejected outright. */
    public const MAX_KILOBYTES = 20480; // 20 MB

    private const DISK = 'local';

    private const ALLOWED_MIME = [
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

    public function __construct(private readonly NotificationEngineService $notifications)
    {
    }

    // ── Read ───────────────────────────────────────────────────────────────

    /**
     * List the documents of a location, honouring the caller's clearance.
     *
     * @return \Illuminate\Support\Collection<int, LocationDocument>
     */
    public function listFor(CustomerLocation $location, User $actor)
    {
        return $this->visibleQuery($actor)
            ->where('location_id', $location->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function findVisible(CustomerLocation $location, int $documentId, User $actor): LocationDocument
    {
        /** @var LocationDocument|null $document */
        $document = $this->visibleQuery($actor)
            ->where('location_id', $location->id)
            ->whereKey($documentId)
            ->first();

        if (! $document) {
            throw ValidationException::withMessages([
                'document' => __('locations.document.not_found'),
            ]);
        }

        return $document;
    }

    /**
     * Stream the file for download.
     *
     * @return array{path: string, name: string, mime: string}
     */
    public function download(LocationDocument $document): array
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

    // ── Write ──────────────────────────────────────────────────────────────

    public function store(
        CustomerLocation $location,
        User $actor,
        UploadedFile $file,
        ?string $title = null,
        string|FileVisibilityEnum|null $visibility = null,
        string|FileCategoryEnum|null $category = null
    ): LocationDocument {
        $visibility = $visibility instanceof FileVisibilityEnum
            ? $visibility
            : (FileVisibilityEnum::tryFrom((string) $visibility) ?? FileVisibilityEnum::Intern);

        $category = $category instanceof FileCategoryEnum
            ? $category
            : (FileCategoryEnum::tryFrom((string) $category) ?? FileCategoryEnum::Other);

        $originalName = $file->getClientOriginalName();

        $path = $file->store("location-documents/{$location->id}", self::DISK);

        $document = LocationDocument::create([
            'location_id'   => $location->id,
            'uploaded_by'   => $actor->id,
            'title'         => $title ?: pathinfo($originalName, PATHINFO_FILENAME),
            'file_path'     => $path,
            'original_name' => $originalName,
            'mime_type'     => $file->getClientMimeType() ?: 'application/octet-stream',
            'size_bytes'    => (int) $file->getSize(),
            'visibility'    => $visibility->value,
            'category'      => $category->value,
        ]);

        $this->notifyAdmins(
            $location,
            $actor,
            __('locations.document.uploaded_title', ['title' => $document->displayTitle()]),
            __('locations.document.uploaded_body', [
                'location' => $location->name,
                'actor'    => $actor->name,
            ]),
            ['location_id' => $location->id, 'document_id' => $document->id]
        );

        return $document;
    }

    /**
     * Re-file an existing document. The old blob is always removed.
     */
    public function replace(LocationDocument $document, UploadedFile $file): LocationDocument
    {
        $previous = $document->file_path;

        $document->update([
            'file_path'     => $file->store("location-documents/{$document->location_id}", self::DISK),
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getClientMimeType() ?: $document->mime_type,
            'size_bytes'    => (int) $file->getSize(),
        ]);

        if ($previous && $previous !== $document->file_path) {
            Storage::disk(self::DISK)->delete($previous);
        }

        return $document->fresh();
    }

    public function updateMeta(LocationDocument $document, array $attributes): LocationDocument
    {
        $document->fill(array_filter(
            $attributes,
            fn ($key) => in_array($key, ['title', 'visibility', 'category'], true),
            ARRAY_FILTER_USE_KEY
        ));

        $document->save();

        return $document->fresh();
    }

    public function delete(LocationDocument $document): void
    {
        Storage::disk(self::DISK)->delete($document->file_path);
        $document->delete();
    }

    // ── Validation ─────────────────────────────────────────────────────────

    /**
     * @return array<string, array<int, string>>
     */
    public static function uploadRules(): array
    {
        return [
            'file'       => ['required', 'file', 'max:'.self::MAX_KILOBYTES],
            'file.mimes' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_MIME)],
            'title'      => ['nullable', 'string', 'max:191'],
            'visibility' => ['nullable', 'in:'.implode(',', FileVisibilityEnum::values())],
            'category'   => ['nullable', 'in:'.implode(',', FileCategoryEnum::values())],
        ];
    }

    // ── Private ────────────────────────────────────────────────────────────

    /**
     * Restrict a document query to what the given actor is cleared to see.
     */
    private function visibleQuery(User $actor)
    {
        $query = LocationDocument::query();

        if (! $actor->isAdministrator()) {
            $query->where('visibility', FileVisibilityEnum::Extern->value);
        }

        return $query;
    }

    private function downloadName(LocationDocument $document): string
    {
        $extension = pathinfo((string) $document->original_name, PATHINFO_EXTENSION);
        $base      = Str::slug($document->displayTitle()) ?: 'dokument';

        return $extension ? "{$base}.{$extension}" : $base;
    }

    private function notifyAdmins(CustomerLocation $location, User $actor, string $title, string $body, array $payload): void
    {
        $this->notifications->notifyAdmins(
            \App\Enums\AdminNotificationTypeEnum::System,
            $title,
            $body,
            $payload,
            'location_document:'.$location->id.':'.$actor->id.':'.now()->toDateString()
        );
    }
}
