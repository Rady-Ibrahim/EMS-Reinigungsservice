<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\ExtraAuftragAssignee;
use App\Models\FixObject;
use App\Models\FixObjectSchedule;
use App\Models\User;
use App\Services\ReassignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReassignmentController extends Controller
{
    public function __construct(private readonly ReassignmentService $service)
    {
    }

    // ── Contract-level reassign (whole Fixobjekt) ──────────────────────────

    public function createContract(FixObject $fixObject): View
    {
        $fixObject->load(['assignments.user', 'customer:id,name']);

        return view('admin.calendar.reassign_contract', [
            'fixObject' => $fixObject,
            'employees' => $this->employees(),
        ]);
    }

    public function storeContract(Request $request, FixObject $fixObject): RedirectResponse
    {
        $request->validate([
            'user_id'        => ['required', 'exists:users,id'],
            'assigned_from'  => ['required', 'date'],
            'assigned_until' => ['nullable', 'date', 'after_or_equal:assigned_from'],
            'reason'         => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->service->reassignContract(
                $fixObject,
                $request->integer('user_id'),
                $request->input('assigned_from'),
                $request->input('assigned_until'),
                $request->input('reason'),
                (bool) $request->input('force', false)
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.fix-objects.show', $fixObject)
                         ->with('success', __('messages.success'));
    }

    // ── Per-day schedule reassign ──────────────────────────────────────────

    public function createSchedule(FixObjectSchedule $schedule): View
    {
        $schedule->load(['fixObject:id,title', 'scheduleAssignments.user:id,name']);
        $schedule->load(['fixObject.assignments.user:id,name']);

        return view('admin.calendar.reassign_schedule', [
            'schedule'  => $schedule,
            'fixObject' => $schedule->fixObject,
            'employees' => $this->employees(),
            'current'   => $schedule->effectiveEmployeeIds(),
        ]);
    }

    public function storeSchedule(Request $request, FixObjectSchedule $schedule): RedirectResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'reason'  => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->service->reassignSchedule(
                $schedule,
                $request->integer('user_id'),
                $request->input('reason'),
                (bool) $request->input('force', false)
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('admin.calendar.schedules.reassign', $schedule)
            ->with('success', __('messages.success'));
    }

    // ── Extra-Auftrag assignee swap ────────────────────────────────────────

    public function createExtraAssignee(ExtraAuftragAssignee $assignee): View
    {
        $assignee->load(['user:id,name', 'extraAuftrag:id,title,scheduled_date,scheduled_time_start']);

        return view('admin.calendar.reassign_extra_assignee', [
            'assignee'  => $assignee,
            'employees' => $this->employees(),
        ]);
    }

    public function storeExtraAssignee(Request $request, ExtraAuftragAssignee $assignee): RedirectResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        try {
            $this->service->reassignExtraAssignee(
                $assignee,
                $request->integer('user_id'),
                (bool) $request->input('force', false)
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.extra-auftraege.show', $assignee->extra_auftrag_id)
                         ->with('success', __('messages.success'));
    }

    private function employees(): \Illuminate\Support\Collection
    {
        return User::whereIn('role', [RoleEnum::Vorarbeiter->value, RoleEnum::Mitarbeiter->value])
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}