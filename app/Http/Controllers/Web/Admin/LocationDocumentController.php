<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerLocation;
use App\Models\LocationDocument;
use App\Services\LocationDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LocationDocumentController extends Controller
{
    public function __construct(private readonly LocationDocumentService $documents)
    {
    }

    /**
     * GET /admin/locations/{location}/documents
     */
    public function index(CustomerLocation $location): RedirectResponse
    {
        return redirect()->route('locations.show', $location)
            ->withFragment('documents');
    }

    /**
     * GET /admin/locations/{location}/documents/{document}/download
     */
    public function download(CustomerLocation $location, LocationDocument $document): BinaryFileResponse
    {
        $this->authorizeDocument($location, $document);

        $file = $this->documents->download($document);

        return response()->download($file['path'], $file['name'], [
            'Content-Type' => $file['mime'],
        ]);
    }

    /**
     * POST /admin/locations/{location}/documents
     */
    public function store(Request $request, CustomerLocation $location): RedirectResponse
    {
        $validated = $request->validate(LocationDocumentService::uploadRules());

        $this->documents->store(
            $location,
            $request->user(),
            $validated['file'],
            $validated['title'] ?? null,
            $validated['visibility'] ?? null,
            $validated['category'] ?? null,
        );

        return back()->with('success', __('locations.document.uploaded'));
    }

    /**
     * PATCH /admin/locations/{location}/documents/{document}
     * Re-title / re-classify / change visibility.
     */
    public function update(Request $request, CustomerLocation $location, LocationDocument $document): RedirectResponse
    {
        $this->authorizeDocument($location, $document);

        $validated = $request->validate([
            'title'      => ['required', 'string', 'max:191'],
            'visibility' => ['required', Rule::in(\App\Enums\FileVisibilityEnum::values())],
            'category'   => ['nullable', Rule::in(\App\Enums\FileCategoryEnum::values())],
        ]);

        $this->documents->updateMeta($document, $validated);

        return back()->with('success', __('locations.document.updated'));
    }

    /**
     * DELETE /admin/locations/{location}/documents/{document}
     */
    public function destroy(CustomerLocation $location, LocationDocument $document): RedirectResponse
    {
        $this->authorizeDocument($location, $document);

        $this->documents->delete($document);

        return back()->with('success', __('locations.document.deleted'));
    }

    /**
     * A document may only ever be reached through the location that owns it,
     * otherwise an id from another customer could be attached to this URL.
     */
    private function authorizeDocument(CustomerLocation $location, LocationDocument $document): void
    {
        abort_if($document->location_id !== $location->id, 404);
    }
}
