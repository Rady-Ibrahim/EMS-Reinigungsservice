<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FixObject\StoreFixObjectRequest;
use App\Http\Requests\Admin\FixObject\UpdateFixObjectRequest;
use App\Models\Customer;
use App\Models\FixObject;
use App\Models\FixObjectAssignment;
use App\Models\User;
use App\Services\ContractHoursCalculator;
use App\Services\FixObjectService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FixObjectController extends Controller
{
    public function __construct(
        private readonly FixObjectService        $service,
        private readonly ContractHoursCalculator $calculator
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
        $data = $this->prepareFinancialData($request->validated());
        $fixObject = $this->service->create($data);

        return redirect()->route('admin.fix-objects.show', $fixObject)
                         ->with('success', __('messages.success'));
    }

    public function show(FixObject $fixObject): View
    {
        $fixObject->load(['customer', 'location', 'assignments.user']);

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
        $fixObject->load(['customer', 'location']);
        return view('admin.fix_objects.edit', compact('fixObject', 'customers'));
    }

    public function update(UpdateFixObjectRequest $request, FixObject $fixObject): RedirectResponse
    {
        $data = $this->prepareFinancialData($request->validated());
        $this->service->update($fixObject, $data);

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
