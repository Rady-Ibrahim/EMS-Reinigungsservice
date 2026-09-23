<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExtraAuftrag\StoreExtraAuftragRequest;
use App\Http\Requests\Admin\ExtraAuftrag\UpdateExtraAuftragRequest;
use App\Models\Customer;
use App\Models\ExtraAuftrag;
use App\Models\User;
use App\Services\ExtraAuftragService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ExtraAuftragController extends Controller
{
    public function __construct(private readonly ExtraAuftragService $service)
    {
    }

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
                         ->get(['id', 'name', 'role']);

        return view('admin.extra_auftraege.create', compact('customers', 'employees'));
    }

    public function store(StoreExtraAuftragRequest $request): RedirectResponse
    {
        $validated  = $request->validated();
        $assignees  = $validated['assignees'];
        $orderData  = $this->prepareOrderData(array_diff_key($validated, ['assignees' => true]));

        $order = $this->service->create($orderData, $assignees);

        return redirect()->route('admin.extra-auftraege.show', $order)
                         ->with('success', __('messages.success'));
    }

    public function show(ExtraAuftrag $extraAuftrag): View
    {
        $extraAuftrag->load([
            'customer', 'location', 'creator',
            'assignees.user.employeeProfile',
            'executions.employee',
            'travelTracks.employee',
        ]);

        return view('admin.extra_auftraege.show', compact('extraAuftrag'));
    }

    public function edit(ExtraAuftrag $extraAuftrag): View
    {
        $customers = Customer::active()->orderBy('name')->get(['id', 'name']);
        $employees = User::whereIn('role', ['vorarbeiter', 'mitarbeiter'])
                         ->where('is_active', true)
                         ->orderBy('name')
                         ->get(['id', 'name', 'role']);

        $extraAuftrag->load(['assignees']);
        return view('admin.extra_auftraege.edit', compact('extraAuftrag', 'customers', 'employees'));
    }

    public function update(UpdateExtraAuftragRequest $request, ExtraAuftrag $extraAuftrag): RedirectResponse
    {
        $validated = $request->validated();
        $assignees = $validated['assignees'] ?? null;
        $orderData = $this->prepareOrderData(array_diff_key($validated, ['assignees' => true]));

        $this->service->update($extraAuftrag, $orderData, $assignees);

        return redirect()->route('admin.extra-auftraege.show', $extraAuftrag)
                         ->with('success', __('messages.success'));
    }

    public function cancel(Request $request, ExtraAuftrag $extraAuftrag): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->service->cancel($extraAuftrag, $request->input('reason'));

        return back()->with('success', 'Auftrag storniert.');
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function prepareOrderData(array $data): array
    {
        $data['created_by'] = Auth::id();

        foreach (['price', 'internal_cost'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (string) $data[$field];
            }
        }

        // Build checklist template from submitted tasks
        if (isset($data['checklist_template']) && is_array($data['checklist_template'])) {
            $data['checklist_template'] = array_map(fn($item) => [
                'task'      => $item['task'],
                'completed' => false,
            ], $data['checklist_template']);
        }

        return $data;
    }
}
