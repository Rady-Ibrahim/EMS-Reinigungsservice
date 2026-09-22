<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Employee\StoreEmployeeRequest;
use App\Http\Requests\Admin\Employee\UpdateEmployeeRequest;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $service)
    {
    }

    public function index(): View
    {
        $employees = $this->service->paginate();
        return view('admin.employees.index', compact('employees'));
    }

    public function create(): View
    {
        return view('admin.employees.create');
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());
        return redirect()->route('admin.employees.index')
                         ->with('success', __('messages.success'));
    }

    public function show(User $employee): View
    {
        // Security: only non-admin users are "employees"
        abort_if($employee->isAdministrator(), 404);
        $employee->load(['employeeProfile', 'auditLogs.user']);
        return view('admin.employees.show', compact('employee'));
    }

    public function edit(User $employee): View
    {
        abort_if($employee->isAdministrator(), 404);
        $employee->load('employeeProfile');
        return view('admin.employees.edit', compact('employee'));
    }

    public function update(UpdateEmployeeRequest $request, User $employee): RedirectResponse
    {
        abort_if($employee->isAdministrator(), 404);
        $this->service->update($employee, $request->validated());
        return redirect()->route('admin.employees.show', $employee)
                         ->with('success', __('messages.success'));
    }

    public function destroy(User $employee): RedirectResponse
    {
        abort_if($employee->isAdministrator(), 404);
        $this->service->delete($employee);
        return redirect()->route('admin.employees.index')
                         ->with('success', __('messages.employees.deleted'));
    }
}
