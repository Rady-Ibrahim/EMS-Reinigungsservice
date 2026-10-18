<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\FileCategoryEnum;
use App\Enums\FileVisibilityEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FixObject\StoreFixObjectRequest;
use App\Http\Requests\Admin\FixObject\UpdateFixObjectRequest;
use App\Models\Customer;
use App\Models\FixObject;
use App\Models\FixObjectAssignment;
use App\Models\User;
use App\Services\ContractHoursCalculator;
use App\Services\DocumentService;
use App\Services\FixObjectService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FixObjectController extends Controller
{
    private const DOCUMENT_FIELDS = [
        'document', 'document_title', 'document_visibility', 'document_category', 'document_notes',
    ];

    public function __construct(
        private readonly FixObjectService        $service,
        private readonly ContractHoursCalculator $calculator,
        private readonly DocumentService         $documents
    ) {
    }

    public function index(): View
    {
        $fixObjects = $this->service->paginate();
        return view('admin.fix_objects.index', compact('fixObjects'));
    }

    public function create(): View
    {
        $customers = Customer::active()->orderBy('name')->get(['id', 'name']);
        return view('admin.fix_objects.create', compact('customers'));
    }

    public function store(StoreFixObjectRequest $request): RedirectResponse
    {
        $data = $this->prepareFinancialData(collect($request->validated())->except(self::DOCUMENT_FIELDS)->all());
        $fixObject = $this->service->create($data);

        $this->storeDocument($request, $fixObject);

        return redirect()->route('admin.fix-objects.show', $fixObject)
                         ->with('success', __('messages.success'));
    }

    public function show(FixObject $fixObject): View
    {
        $fixObject->load(['customer', 'location', 'assignments.user', 'documents.uploader']);

        $now           = Carbon::now();
        $contractHours = $this->calculator->monthlyHours($fixObject, $now->year, $now->month);

        $schedules = $fixObject->schedules()
            ->forMonth($now->year, $now->month)
            ->with('executions.employee')
            ->orderBy('scheduled_date')
            ->get();

        return view('admin.fix_objects.show', compact('fixObject', 'contractHours', 'schedules'));
    }

    public function edit(FixObject $fixObject): View
    {
        $customers = Customer::active()->orderBy('name')->get(['id', 'name']);
        $fixObject->load(['customer', 'location', 'documents']);
        return view('admin.fix_objects.edit', compact('fixObject', 'customers'));
    }

    public function update(UpdateFixObjectRequest $request, FixObject $fixObject): RedirectResponse
    {
        $data = $this->prepareFinancialData(collect($request->validated())->except(self::DOCUMENT_FIELDS)->all());
        $this->service->update($fixObject, $data);

        $this->storeDocument($request, $fixObject);

        return redirect()->route('admin.fix-objects.show', $fixObject)
                         ->with('success', __('messages.success'));
    }

    public function destroy(FixObject $fixObject): RedirectResponse
    {
        $this->service->delete($fixObject);
        return redirect()->route('admin.fix-objects.index')
                         ->with('success', __('messages.success'));
    }

    // ── Assignments ────────────────────────────────────────────────────────

    public function storeAssignment(Request $request, FixObject $fixObject): RedirectResponse
    {
        $request->validate([
            'user_id'       => ['required', 'exists:users,id'],
            'assigned_from' => ['required', 'date'],
            'assigned_until'=> ['nullable', 'date', 'after_or_equal:assigned_from'],
        ]);

        $this->service->assignEmployee(
            $fixObject,
            $request->integer('user_id'),
            $request->input('assigned_from'),
            $request->input('assigned_until')
        );

        return back()->with('success', __('messages.success'));
    }

    public function destroyAssignment(FixObject $fixObject, FixObjectAssignment $assignment): RedirectResponse
    {
        $this->service->removeAssignment($assignment);
        return back()->with('success', __('messages.success'));
    }

    // ── Schedules ──────────────────────────────────────────────────────────

    public function generateSchedules(Request $request, FixObject $fixObject): RedirectResponse
    {
        $request->validate([
            'year'  => ['required', 'integer', 'min:2020', 'max:2099'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $count = app(\App\Services\ScheduleGeneratorService::class)
            ->generateForMonth($fixObject, $request->integer('year'), $request->integer('month'));

        return back()->with('success', "✓ {$count} Termine wurden erstellt.");
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /**
     * Persist the (optional) contract/document uploaded with the form.
     */
    private function storeDocument(Request $request, FixObject $fixObject): void
    {
        if (! $request->hasFile('document')) {
            return;
        }

        $this->documents->store(
            $fixObject,
            $request->user(),
            $request->file('document'),
            $request->input('document_title'),
            $request->input('document_visibility') ?: FileVisibilityEnum::Extern->value,
            $request->input('document_category') ?: FileCategoryEnum::Contract->value,
            $request->input('document_notes')
        );
    }

    /**
     * Convert plaintext financial floats to strings for encrypted cast storage.
     */
    private function prepareFinancialData(array $data): array
    {
        foreach (['price_per_month', 'price_per_hour', 'internal_cost', 'profit_margin'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (string) $data[$field];
            }
        }
        return $data;
    }
}
