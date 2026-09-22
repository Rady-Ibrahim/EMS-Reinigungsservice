@extends('admin.layouts.app')
@section('title', $employee->name)

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h1>
        @if($employee->employeeProfile)
        <span class="color-dot" style="background:{{ $employee->employeeProfile->calendar_color }};width:18px;height:18px"></span>
        @endif
        {{ $employee->name }}
    </h1>
    <a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-secondary">Bearbeiten</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
    <div class="card">
        <div class="card-title" style="margin-bottom:.75rem">Account</div>
        <table>
            <tr><td style="color:#64748b;width:130px">E-Mail</td><td>{{ $employee->email }}</td></tr>
            <tr><td style="color:#64748b">Rolle</td><td><span class="badge badge-gray">{{ $employee->role->label() }}</span></td></tr>
            <tr><td style="color:#64748b">Sprache</td><td>{{ strtoupper($employee->locale) }}</td></tr>
            <tr><td style="color:#64748b">Status</td><td>
                @if($employee->is_active)
                    <span class="badge badge-green">Aktiv</span>
                @else
                    <span class="badge badge-red">Inaktiv</span>
                @endif
            </td></tr>
            <tr><td style="color:#64748b">Letzter Login</td><td>{{ $employee->last_login_at?->format('d.m.Y H:i') ?? '—' }}</td></tr>
        </table>
    </div>

    @if($employee->employeeProfile)
    <div class="card">
        <div class="card-title" style="margin-bottom:.75rem">Profil</div>
        <table>
            <tr><td style="color:#64748b;width:130px">Mitarb.-Nr.</td><td>{{ $employee->employeeProfile->employee_number ?? '—' }}</td></tr>
            <tr><td style="color:#64748b">Telefon</td><td>{{ $employee->employeeProfile->phone ?? '—' }}</td></tr>
            <tr><td style="color:#64748b">Vertragsart</td><td>{{ $employee->employeeProfile->contract_type?->label() ?? '—' }}</td></tr>
            <tr><td style="color:#64748b">Eintrittsdatum</td><td>{{ $employee->employeeProfile->joined_at?->format('d.m.Y') ?? '—' }}</td></tr>
            {{-- Sensitive fields shown only to admin in dedicated view --}}
            <tr><td style="color:#64748b">Stundenlohn</td><td>
                @if($employee->employeeProfile->hourly_rate)
                    {{ number_format((float)$employee->employeeProfile->hourly_rate, 2) }} €/h
                @else
                    —
                @endif
            </td></tr>
            <tr><td style="color:#64748b">IBAN</td><td>
                @if($employee->employeeProfile->iban)
                    <span style="font-family:monospace">{{ substr($employee->employeeProfile->iban, 0, 8) }}••••••••••</span>
                @else —
                @endif
            </td></tr>
        </table>
    </div>
    @endif
</div>
@endsection
