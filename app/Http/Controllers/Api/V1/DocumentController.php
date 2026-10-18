<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FileVisibilityEnum;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\ExtraAuftrag;
use App\Models\FixObject;
use App\Services\DocumentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile download of externally visible documents attached to a fix object or
 * an extra order. Only `extern` documents are ever served here; intern
 * documents return 404 regardless of the caller.
 */
class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    public function downloadFixObject(Request $request, FixObject $fixObject, Document $document): BinaryFileResponse
    {
        $userId = $request->user()->id;

        abort_unless(
            $fixObject->assignments()->where('user_id', $userId)->active()->exists(),
            Response::HTTP_NOT_FOUND
        );

        return $this->download($fixObject, $document);
    }

    public function downloadExtraAuftrag(Request $request, ExtraAuftrag $extraAuftrag, Document $document): BinaryFileResponse
    {
        abort_unless($extraAuftrag->isAssigned($request->user()->id), Response::HTTP_NOT_FOUND);

        return $this->download($extraAuftrag, $document);
    }

    private function download(Model $owner, Document $document): BinaryFileResponse
    {
        $owned = $document->documentable_type === $owner->getMorphClass()
            && (int) $document->documentable_id === (int) $owner->getKey();

        abort_unless(
            $owned && $document->visibility === FileVisibilityEnum::Extern,
            Response::HTTP_NOT_FOUND
        );

        try {
            $file = $this->documents->download($document);
        } catch (ValidationException) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->download($file['path'], $file['name'], ['Content-Type' => $file['mime']]);
    }
}
