@extends('admin.layouts.app')
@section('title', __('messages.employees.title'))

@section('content')
<div class="card">
    <div class="card-header">
        <h1 class="card-title">{{ __('messages.employees.title') }}</h1>
        <a href="{{ route('admin.employees.create') }}" class="btn btn-primary">+ {{ __('messages.employees.create') }}</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Farbe</th>
                <th>Name</th>
                <th>E-Mail</th>
                <th>Rolle</th>
                <th>Vertrag</th>
                <th>Status</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            @forelse($employees as $employee)
            <tr>
                <td>
                    @if($employee->employeeProfile)
                    <span class="color-dot" style="background:{{ $employee->employeeProfile->calendar_color }}"></span>
                    @endif
                </td>
                <td>
                    <a href="{{ route('admin.employees.show', $employee) }}">{{ $employee->name }}</a>
                </td>
                <td>{{ $employee->email }}</td>
                <td><span class="badge badge-gray">{{ $employee->role->label() }}</span></td>
                <td>{{ $employee->employeeProfile?->contract_type?->label() ?? '—' }}</td>
                <td>
                    @if($employee->is_active)
                        <span class="badge badge-green">Aktiv</span>
                    @else
                        <span class="badge badge-red">Inaktiv</span>
                    @endif
                </td>
                <td>
                    <div class="actions">
                        <a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-secondary btn-sm">Bearbeiten</a>
                        <form action="{{ route('admin.employees.destroy', $employee) }}" method="POST"
                              onsubmit="return confirm('Wirklich löschen?')" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:2rem">Keine Mitarbeiter gefunden.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">{{ $employees->links() }}</div>
</div>
@endsection
