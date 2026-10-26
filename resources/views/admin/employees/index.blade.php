@extends('admin.layouts.app')
@section('title', __('employee.title'))

@section('content')
<div class="card">
    <div class="card-header">
        <h1 class="card-title">{{ __('employee.title') }}</h1>
        <a href="{{ route('admin.employees.create') }}" class="btn btn-primary">+ {{ __('employee.create_title') }}</a>
    </div>

    <table class="mobile-card-table">
        <thead>
            <tr>
                <th>{{ __('employee.color') }}</th>
                <th>{{ __('employee.name') }}</th>
                <th>{{ __('employee.email') }}</th>
                <th>{{ __('employee.role') }}</th>
                <th>{{ __('employee.contract') }}</th>
                <th>{{ __('employee.status') }}</th>
                <th>{{ __('employee.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($employees as $employee)
            <tr>
                <td class="mobile-card-optional" data-label="{{ __('employee.color') }}">
                    @if($employee->employeeProfile)
                    <span class="color-dot" style="background:{{ $employee->employeeProfile->calendar_color }}"></span>
                    @endif
                </td>
                <td data-label="{{ __('employee.name') }}">
                    <a href="{{ route('admin.employees.show', $employee) }}">{{ $employee->name }}</a>
                </td>
                <td data-label="{{ __('employee.email') }}">{{ $employee->email }}</td>
                <td data-label="{{ __('employee.role') }}"><span class="badge badge-gray">{{ $employee->role->label() }}</span></td>
                <td data-label="{{ __('employee.contract') }}">{{ $employee->employeeProfile?->contract_type?->label() ?? '—' }}</td>
                <td data-label="{{ __('employee.status') }}">
                    @if($employee->is_active)
                        <span class="badge badge-green">{{ __('employee.status_active') }}</span>
                    @else
                        <span class="badge badge-red">{{ __('employee.status_inactive') }}</span>
                    @endif
                </td>
                <td data-label="{{ __('employee.actions') }}">
                    <div class="actions">
                        <a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-secondary btn-sm">{{ __('employee.edit') }}</a>
                        <form action="{{ route('admin.employees.destroy', $employee) }}" method="POST"
                              onsubmit="return confirm('{{ __('employee.delete_warning') }}')" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">{{ __('employee.delete') }}</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr class="mobile-table-empty"><td colspan="7" style="text-align:center;color:#94a3b8;padding:2rem">{{ __('employee.no_employees') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">{{ $employees->links() }}</div>
</div>
@endsection