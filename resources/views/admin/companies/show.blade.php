@extends('admin.layouts.app')
@section('title', $company->name)

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h1>{{ $company->name }}</h1>
    <a href="{{ route('admin.companies.edit', $company) }}" class="btn btn-secondary">{{ __('company.edit') }}</a>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">{{ __('company.details') }}</span>
    </div>
    <table>
        <tr><td style="color:#64748b;width:200px">{{ __('company.contact_person') }}</td><td>{{ $company->contact_person ?? '—' }}</td></tr>
        <tr><td style="color:#64748b">{{ __('company.phone') }}</td><td>{{ $company->phone ?? '—' }}</td></tr>
        <tr><td style="color:#64748b">{{ __('company.notes') }}</td><td>{{ $company->notes ?? '—' }}</td></tr>
    </table>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">{{ __('company.employees') }} ({{ $company->users->count() }})</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('employee.name') ?? 'Name' }}</th>
                <th>{{ __('employee.role') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($company->users as $employee)
            <tr>
                <td>{{ $employee->id }}</td>
                <td><a href="{{ route('admin.employees.show', $employee) }}">{{ $employee->name }}</a></td>
                <td>{{ $employee->role->label() }}</td>
            </tr>
            @empty
            <tr><td colspan="3" style="text-align:center;color:#94a3b8;padding:2rem">{{ __('company.no_employees') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:1rem">
        <a href="{{ route('admin.companies.index') }}" class="btn btn-secondary">{{ __('company.back') }}</a>
    </div>
</div>
@endsection
