<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerLocation;
use App\Models\LocationDocument;
use App\Services\LocationDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile access to the documents of a customer location.
 *
 * Field staff only ever receive `extern` documents. `intern` documents are
 * invisible here — the listing is filtered in the service layer and a direct
 * id lookup returns 404, so an intern document can never be exfiltrated from
 * the app.
 */
class LocationDocumentController extends Controller
{
    public function __construct(private readonly LocationDocumentService $documents)
    {
    }

    /**
     * GET /api/v1/locations/{location}/documents
     */
    public function index(Request $request, CustomerLocation $location): JsonResponse
    {
        abort_unless($location->is_active, Response::HTTP_NOT_FOUND);

        // The mobile client sends `visibility=extern`. It is accepted for
        // readability but can never widen the result set: the service layer
        // already restricts the listing to what this user may see, and any
        // other value is rejected rather than silently ignored.
        $requested = $request->query('visibility');

        if ($requested !== null && $requested !== 'extern') {
            abort(Response::HTTP_FORBIDDEN, 'Nur externe Dokumente sind mobil sichtbar.');
        }

        $documents = $this->documents->listFor($location, $request->user());

        return response()->json([
            'data' => $documents->map(fn(LocationDocument $d) => $this->format($d, $location))->values(),
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/v1/locations/{location}/documents/{document}/download
     */
    public function download(Request $request, CustomerLocation $location, LocationDocument $document)
    {
        abort_unless($location->is_active, Response::HTTP_NOT_FOUND);

        try {
            $this->documents->findVisible($location, $document->id, $request->user());
        } catch (\Illuminate\Validation\ValidationException) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $file = $this->documents->download($document);

        return response()->download($file['path'], $file['name'], [
            'Content-Type' => $file['mime'],
        ]);
    }

    private function format(LocationDocument $document, CustomerLocation $location): array
    {
        return [
            'id'            => $document->id,
            'title'         => $document->displayTitle(),
            'original_name' => $document->original_name,
            'mime_type'     => $document->mime_type,
            'size_bytes'    => (int) $document->size_bytes,
            'size_kb'       => $document->sizeInKb(),
            'visibility'    => $document->visibility->value,
            'category'      => $document->category->value,
            'download_url'  => route('api.v1.locations.documents.download', [$location->id, $document->id]),
            'uploaded_at'   => $document->created_at?->toIso8601String(),
        ];
    }
}
