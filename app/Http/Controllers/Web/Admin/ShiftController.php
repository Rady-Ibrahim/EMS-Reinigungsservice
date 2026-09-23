<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Shift\StoreShiftRequest;
use App\Http\Requests\Admin\Shift\UpdateShiftRequest;
use App\Models\EmployeeShift;
use App\Enums\RoleEnum;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public function __construct(private readonly ShiftService $service)
    {
    }

    public function index(): View
    {
        $shifts = EmployeeShift::with(['user:id,name', 'creator:id,name'])
            ->orderByDesc('start_at')
            ->paginate(25);

        return view('admin.calendar.shifts', compact('shifts'));
    }

    public function create(): View
    {
        $employees = $this->employees();

        return view('admin.calendar.shift_form', [
            'shift'     => new EmployeeShift(),
            'employees' => $employees,
        ]);
    }

    public function store(StoreShiftRequest $request): RedirectResponse
    {
        try {
            $this->service->create(array_merge($request->validated(), [
                'created_by' => auth()->id(),
                'status'     => \App\Enums\ShiftStatusEnum::Planned,
            ]), (bool) $request->input('force', false));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.calendar.shifts.index')
                         ->with('success', __('messages.success'));
    }

    public function edit(EmployeeShift $shift): View
    {
        return view('admin.calendar.shift_form', [
            'shift'     => $shift,
            'employees' => $this->employees(),
        ]);
    }

    public function update(UpdateShiftRequest $request, EmployeeShift $shift): RedirectResponse
    {
        try {
            $this->service->update($shift, $request->validated(), (bool) $request->input('force', false));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.calendar.shifts.index')
                         ->with('success', __('messages.success'));
    }

    public function destroy(EmployeeShift $shift): RedirectResponse
    {
        $this->service->delete($shift);

        return redirect()->route('admin.calendar.shifts.index')
                         ->with('success', __('messages.success'));
    }

    public function cancel(EmployeeShift $shift): RedirectResponse
    {
        $this->service->cancel($shift);

        return back()->with('success', __('messages.success'));
    }

    private function employees(): \Illuminate\Support\Collection
    {
        return User::whereIn('role', [RoleEnum::Vorarbeiter->value, RoleEnum::Mitarbeiter->value])
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}