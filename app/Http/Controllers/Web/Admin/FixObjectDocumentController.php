<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\FileCategoryEnum;
use App\Enums\FileVisibilityEnum;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\FixObject;
use App\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FixObjectDocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    public function store(Request $request, FixObject $fixObject): RedirectResponse
    {
        $request->validate(array_merge(DocumentService::uploadRules(), [
            'document_visibility' => ['nullable', 'in:'.implode(',', FileVisibilityEnum::values())],
            'document_category' => ['nullable', 'in:'.implode(',', FileCategoryEnum::values())],
        ]));

        $this->documents->store(
            $fixObject,
            $request->user(),
            $request->file('document'),
            $request->input('document_title'),
            $request->input('document_visibility') ?: FileVisibilityEnum::Extern->value,
            $request->input('document_category') ?: FileCategoryEnum::Contract->value,
            $request->input('document_notes')
        );

        return back()->with('success', __('messages.success'));
    }

    public function download(FixObject $fixObject, Document $document): BinaryFileResponse
    {
        $this->assertOwned($fixObject, $document);

        $file = $this->documents->download($document);

        return response()->download($file['path'], $file['name'], ['Content-Type' => $file['mime']]);
    }

    public function destroy(FixObject $fixObject, Document $document): RedirectResponse
    {
        $this->assertOwned($fixObject, $document);

        $this->documents->delete($document);

        return back()->with('success', __('messages.success'));
    }

    private function assertOwned(FixObject $fixObject, Document $document): void
    {
        abort_unless(
            $document->documentable_type === $fixObject->getMorphClass()
                && (int) $document->documentable_id === (int) $fixObject->getKey(),
            404
        );
    }
}
