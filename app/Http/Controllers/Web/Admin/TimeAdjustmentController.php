<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimeAdjustmentRequest;
use App\Models\User;
use App\Services\TimeAdjustmentService;
use App\Services\TimeTrackingEngine;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TimeAdjustmentController extends Controller
{
    public function __construct(
        private readonly TimeAdjustmentService $service,
        private readonly TimeTrackingEngine    $engine
    ) {
    }

    // ── Time Adjustments ───────────────────────────────────────────────────

    public function index(): View
    {
        $pending = $this->service->pendingRequests(10);
        $all     = $this->service->allRequests(20);

        return view('admin.time_adjustments.index', compact('pending', 'all'));
    }

    public function show(TimeAdjustmentRequest $timeAdjustment): View
    {
        $timeAdjustment->load(['employee', 'reviewer']);
        return view('admin.time_adjustments.show', compact('timeAdjustment'));
    }

    public function approve(Request $request, TimeAdjustmentRequest $timeAdjustment): RedirectResponse
    {
        if (! $timeAdjustment->isPending()) {
            return back()->withErrors(['error' => 'Dieser Antrag wurde bereits bearbeitet.']);
        }

        $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->approve($timeAdjustment, Auth::user(), $request->input('admin_note'));

        return redirect()->route('admin.time-adjustments.index')
                         ->with('success', 'Zeitkorrektur genehmigt und angewendet.');
    }

    public function reject(Request $request, TimeAdjustmentRequest $timeAdjustment): RedirectResponse
    {
        if (! $timeAdjustment->isPending()) {
            return back()->withErrors(['error' => 'Dieser Antrag wurde bereits bearbeitet.']);
        }

        $request->validate([
            'admin_note' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->service->reject($timeAdjustment, Auth::user(), $request->input('admin_note'));

        return redirect()->route('admin.time-adjustments.index')
                         ->with('success', 'Zeitkorrektur abgelehnt.');
    }

    // ── Time Tracking Overview ─────────────────────────────────────────────

    public function trackingOverview(Request $request): View
    {
        $request->validate([
            'year'        => ['nullable', 'integer', 'min:2020', 'max:2099'],
            'month'       => ['nullable', 'integer', 'min:1', 'max:12'],
            'employee_id' => ['nullable', 'exists:users,id'],
        ]);

        $year       = $request->integer('year',  now()->year);
        $month      = $request->integer('month', now()->month);
        $employeeId = $request->input('employee_id');

        $employees = User::whereIn('role', ['vorarbeiter', 'mitarbeiter'])
                         ->where('is_active', true)
                         ->orderBy('name')
                         ->get(['id', 'name', 'role']);

        $summaries = collect();
        if ($employeeId) {
            $employee = User::findOrFail($employeeId);
            $totals   = $this->engine->monthlyTotalsForEmployee($employee, $year, $month);
        } else {
            $totals = null;
        }

        return view('admin.time_tracking.overview', compact(
            'employees', 'year', 'month', 'employeeId', 'totals'
        ));
    }
}
