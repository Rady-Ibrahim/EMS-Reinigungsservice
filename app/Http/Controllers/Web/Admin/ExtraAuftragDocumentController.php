<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\FileCategoryEnum;
use App\Enums\FileVisibilityEnum;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\ExtraAuftrag;
use App\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExtraAuftragDocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    /**
     * Upload a single document for the order. The `document_visibility` field
     * decides whether it is admin-only (intern) or shared with the team
     * (extern). The create/edit forms post both sections through this action.
     */
    public function store(Request $request, ExtraAuftrag $extraAuftrag): RedirectResponse
    {
        $request->validate(array_merge(DocumentService::uploadRules(), [
            'document_visibility' => ['nullable', 'in:'.implode(',', FileVisibilityEnum::values())],
            'document_category' => ['nullable', 'in:'.implode(',', FileCategoryEnum::values())],
        ]));

        $this->documents->store(
            $extraAuftrag,
            $request->user(),
            $request->file('document'),
            $request->input('document_title'),
            $request->input('document_visibility') ?: FileVisibilityEnum::Intern->value,
            $request->input('document_category') ?: FileCategoryEnum::Other->value,
            $request->input('document_notes')
        );

        return back()->with('success', __('messages.success'));
    }

    public function download(ExtraAuftrag $extraAuftrag, Document $document): BinaryFileResponse
    {
        $this->assertOwned($extraAuftrag, $document);

        $file = $this->documents->download($document);

        return response()->download($file['path'], $file['name'], ['Content-Type' => $file['mime']]);
    }

    public function destroy(ExtraAuftrag $extraAuftrag, Document $document): RedirectResponse
    {
        $this->assertOwned($extraAuftrag, $document);

        $this->documents->delete($document);

        return back()->with('success', __('messages.success'));
    }

    private function assertOwned(ExtraAuftrag $extraAuftrag, Document $document): void
    {
        abort_unless(
            $document->documentable_type === $extraAuftrag->getMorphClass()
                && (int) $document->documentable_id === (int) $extraAuftrag->getKey(),
            404
        );
    }
}
