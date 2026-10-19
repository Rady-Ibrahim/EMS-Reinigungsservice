<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\FileCategoryEnum;
use App\Enums\FileVisibilityEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExtraAuftrag\StoreExtraAuftragRequest;
use App\Http\Requests\Admin\ExtraAuftrag\UpdateExtraAuftragRequest;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ExtraAuftrag;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\ExtraAuftragService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExtraAuftragController extends Controller
{
    private const DOCUMENT_FIELDS = [
        'intern_document', 'intern_document_title', 'intern_document_category', 'intern_document_notes',
        'extern_document', 'extern_document_title', 'extern_document_category', 'extern_document_notes',
    ];

    public function __construct(
        private readonly ExtraAuftragService $service,
        private readonly DocumentService $documents
    ) {}

    public function index(): View
    {
        $orders = $this->service->paginate();

        return view('admin.extra_auftraege.index', compact('orders'));
    }

    public function create(): View
    {
        $customers = Customer::active()->orderBy('name')->get(['id', 'name']);
        $employees = User::whereIn('role', ['vorarbeiter', 'mitarbeiter'])
            ->where('is_active', true)
            ->orderBy('name')
            ->with('employeeProfile:user_id,calendar_color')
            ->get(['id', 'name', 'role', 'company_id']);

        $companies = Company::orderBy('name')->get(['id', 'name']);

        return view('admin.extra_auftraege.create', compact('customers', 'employees', 'companies'));
    }

    public function store(StoreExtraAuftragRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $assignees = $validated['assignees'];
        $orderData = $this->prepareOrderData(collect($validated)->except(array_merge(['assignees', 'attachment'], self::DOCUMENT_FIELDS))->all());

        $order = $this->service->create($orderData, $assignees);

        if ($request->hasFile('attachment')) {
            $order->update($this->storeAttachment($request->file('attachment')));
        }

        $this->storeDocuments($request, $order);

        return redirect()->route('admin.extra-auftraege.show', $order)
            ->with('success', __('messages.success'));
    }

    public function show(ExtraAuftrag $extraAuftrag): View
    {
        $extraAuftrag->load([
            'customer', 'location', 'company', 'creator',
            'assignees.user.employeeProfile',
            'executions.employee',
            'travelTracks.employee',
            'documents.uploader',
        ]);

        return view('admin.extra_auftraege.show', compact('extraAuftrag'));
    }

    public function edit(ExtraAuftrag $extraAuftrag): View
    {
        $customers = Customer::active()->orderBy('name')->get(['id', 'name']);
        $employees = User::whereIn('role', ['vorarbeiter', 'mitarbeiter'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'company_id']);

        $companies = Company::orderBy('name')->get(['id', 'name']);

        $extraAuftrag->load(['assignees', 'documents']);

        return view('admin.extra_auftraege.edit', compact('extraAuftrag', 'customers', 'employees', 'companies'));
    }

    public function update(UpdateExtraAuftragRequest $request, ExtraAuftrag $extraAuftrag): RedirectResponse
    {
        $validated = $request->validated();
        $assignees = $validated['assignees'] ?? null;
        $orderData = $this->prepareOrderData(collect($validated)->except(array_merge(['assignees', 'attachment'], self::DOCUMENT_FIELDS))->all());

        $this->service->update($extraAuftrag, $orderData, $assignees);

        if ($request->hasFile('attachment')) {
            $this->deleteAttachmentFile($extraAuftrag);
            $extraAuftrag->update($this->storeAttachment($request->file('attachment')));
        }

        $this->storeDocuments($request, $extraAuftrag);

        return redirect()->route('admin.extra-auftraege.show', $extraAuftrag)
            ->with('success', __('messages.success'));
    }

    /**
     * Stream the attached contract / image for download.
     */
    public function downloadAttachment(ExtraAuftrag $extraAuftrag): BinaryFileResponse
    {
        abort_unless($extraAuftrag->attachment_path && Storage::disk('local')->exists($extraAuftrag->attachment_path), 404);

        return response()->download(
            Storage::disk('local')->path($extraAuftrag->attachment_path),
            $extraAuftrag->attachment_name ?: 'attachment',
            ['Content-Type' => $extraAuftrag->attachment_mime ?: 'application/octet-stream']
        );
    }

    public function cancel(Request $request, ExtraAuftrag $extraAuftrag): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->service->cancel($extraAuftrag, $request->input('reason'));

        return back()->with('success', 'Auftrag storniert.');
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /**
     * Persist the optional intern (admin-only) and extern (team-visible)
     * documents uploaded with the order form.
     */
    private function storeDocuments(Request $request, ExtraAuftrag $order): void
    {
        $sections = [
            ['prefix' => 'intern_document', 'visibility' => FileVisibilityEnum::Intern->value],
            ['prefix' => 'extern_document', 'visibility' => FileVisibilityEnum::Extern->value],
        ];

        foreach ($sections as $section) {
            $prefix = $section['prefix'];

            if (! $request->hasFile($prefix)) {
                continue;
            }

            $this->documents->store(
                $order,
                $request->user(),
                $request->file($prefix),
                $request->input($prefix.'_title'),
                $section['visibility'],
                $request->input($prefix.'_category') ?: FileCategoryEnum::Other->value,
                $request->input($prefix.'_notes')
            );
        }
    }

    private function prepareOrderData(array $data): array
    {
        $data['created_by'] = Auth::id();

        foreach (['price', 'price_per_hour', 'profit_margin', 'internal_cost', 'company_payout'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (string) $data[$field];
            }
        }

        // Build checklist template from submitted tasks
        if (isset($data['checklist_template']) && is_array($data['checklist_template'])) {
            $data['checklist_template'] = array_map(fn ($item) => [
                'task' => is_array($item) ? ($item['task'] ?? null) : $item,
                'completed' => false,
            ], $data['checklist_template']);
        }

        return $data;
    }

    /**
     * Persist the uploaded contract/image on the private disk.
     *
     * @return array<string, mixed>
     */
    private function storeAttachment($file): array
    {
        $path = $file->store('extra-auftraege/attachments', 'local');

        return [
            'attachment_path' => $path,
            'attachment_name' => $file->getClientOriginalName(),
            'attachment_mime' => $file->getClientMimeType(),
            'attachment_size' => $file->getSize(),
        ];
    }

    private function deleteAttachmentFile(ExtraAuftrag $order): void
    {
        if ($order->attachment_path) {
            Storage::disk('local')->delete($order->attachment_path);
        }
    }
}
