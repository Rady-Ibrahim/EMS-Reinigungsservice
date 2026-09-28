<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\ComplaintStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\User;
use App\Services\ComplaintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Back-office screen for the Reklamationen queue ("Meldungen").
 *
 * The dashboard links here, so the index supports the same filters as the API
 * plus a per-status tab.
 */
class ComplaintController extends Controller
{
    public function __construct(private readonly ComplaintService $complaints)
    {
    }

    /**
     * GET /admin/complaints
     */
    public function index(Request $request): View
    {
        $filters = $request->validate(ComplaintService::filterRules());

        $complaints = $this->complaints->listFor(
            Auth::user(),
            $filters,
            (int) ($filters['per_page'] ?? 25),
        );

        return view('admin.complaints.index', [
            'complaints' => $complaints,
            'filters'    => $filters,
            'statuses'   => ComplaintStatusEnum::cases(),
            'openCount'  => Complaint::open()->count(),
        ]);
    }

    /**
     * GET /admin/complaints/{complaint}
     */
    public function show(Complaint $complaint): View
    {
        return view('admin.complaints.show', [
            'complaint' => $complaint->load(['location', 'customer', 'assignedEmployee', 'resolver']),
        ]);
    }

    /**
     * GET /admin/complaints/create
     */
    public function create(): View
    {
        return view('admin.complaints.create', [
            'statuses' => ComplaintStatusEnum::cases(),
        ]);
    }

    /**
     * POST /admin/complaints
     */
    public function store(Request $request): RedirectResponse
    {
        $attributes = $request->validate(ComplaintService::storeRules());

        $complaint = $this->complaints->create($attributes, $request->user());

        return redirect()
            ->route('admin.complaints.show', $complaint)
            ->with('success', __('complaints.flash.created'));
    }

    /**
     * PATCH /admin/complaints/{complaint}/status
     */
    public function updateStatus(Request $request, Complaint $complaint): RedirectResponse
    {
        $attributes = $request->validate(ComplaintService::statusRules());

        $status = ComplaintStatusEnum::from($attributes['status']);

        $request->validate([
            'resolution' => [$status === ComplaintStatusEnum::Resolved ? 'required' : 'nullable', 'string', 'max:5000'],
        ], [], [
            'resolution' => __('validation.attributes.resolution'),
        ]);

        $this->complaints->updateStatus($complaint, $status, $attributes, $request->user());

        return back()->with('success', __('complaints.flash.status_updated'));
    }

    /**
     * PATCH /admin/complaints/{complaint}/assign
     */
    public function assign(Request $request, Complaint $complaint): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_employee_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        /** @var User $assignee */
        $assignee = User::findOrFail($validated['assigned_employee_id']);

        $this->complaints->assign($complaint, $assignee, $request->user());

        return back()->with('success', __('complaints.flash.assigned'));
    }
}
