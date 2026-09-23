@extends('admin.layouts.app')
@section('title', 'Zeiterfassung Übersicht')

@section('content')
<h1 style="margin-bottom:1rem">Zeiterfassung Übersicht</h1>

{{-- Filter form --}}
<div class="card" style="margin-bottom:1.25rem">
    <form method="GET" action="{{ route('admin.time-tracking.overview') }}" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="margin-bottom:0;flex:1;min-width:180px">
            <label>Mitarbeiter</label>
            <select name="employee_id">
                <option value="">— Alle —</option>
                @foreach($employees as $emp)
                <option value="{{ $emp->id }}" {{ $employeeId == $emp->id ? 'selected' : '' }}>
                    {{ $emp->name }} ({{ $emp->role->label() }})
                </option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Monat</label>
            <select name="month">
                @for($m = 1; $m <= 12; $m++)
                <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                    {{ Carbon\Carbon::create()->month($m)->locale('de')->monthName }}
                </option>
                @endfor
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Jahr</label>
            <input type="number" name="year" value="{{ $year }}" min="2024" max="2030" style="width:90px">
        </div>
        <button type="submit" class="btn btn-primary" style="margin-bottom:0">Anzeigen</button>
    </form>
</div>

@if($totals && $employeeId)
@php $employee = \App\Models\User::find($employeeId); @endphp
<div class="card">
    <div class="card-title" style="margin-bottom:1rem">
        {{ $employee->name }} —
        {{ \Carbon\Carbon::create()->month($month)->locale('de')->monthName }} {{ $year }}
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem">
        {{-- Total paid --}}
        <div style="background:#eff6ff;border-radius:10px;padding:1.25rem;text-align:center">
            <div style="font-size:2rem;font-weight:700;color:#1e40af">{{ $totals['total_paid_hours'] }}h</div>
            <div style="font-size:.85rem;color:#475569;margin-top:.25rem">Bezahlte Std. gesamt</div>
        </div>
        {{-- Fix hours --}}
        <div style="background:#f0fdf4;border-radius:10px;padding:1.25rem;text-align:center">
            <div style="font-size:2rem;font-weight:700;color:#15803d">{{ $totals['fix_paid_hours'] }}h</div>
            <div style="font-size:.85rem;color:#475569;margin-top:.25rem">
                Fixobjekte ({{ $totals['fix_count'] }} Einsätze)<br>
                <small style="color:#94a3b8">Tatsächlich: {{ $totals['fix_actual_hours'] }}h</small>
            </div>
        </div>
        {{-- Extra hours --}}
        <div style="background:#fefce8;border-radius:10px;padding:1.25rem;text-align:center">
            <div style="font-size:2rem;font-weight:700;color:#a16207">{{ $totals['extra_paid_hours'] }}h</div>
            <div style="font-size:.85rem;color:#475569;margin-top:.25rem">
                Extra-Aufträge ({{ $totals['extra_count'] }} Einsätze)<br>
                <small style="color:#94a3b8">Tatsächlich: {{ $totals['extra_actual_hours'] }}h</small>
            </div>
        </div>
    </div>

    <div style="padding:.75rem;background:#f8fafc;border-radius:8px;font-size:.85rem;color:#475569">
        <strong>Hinweis:</strong> Fixobjekte-Stunden basieren auf den Vertragsstunden (contract_hours_applied),
        nicht auf der tatsächlichen Arbeitszeit. Die tatsächliche Zeit dient nur der internen Nachverfolgung.
    </div>
</div>
@elseif(!$employeeId)
<div class="card" style="text-align:center;color:#94a3b8;padding:3rem">
    Wählen Sie einen Mitarbeiter aus, um die Zeitauswertung anzuzeigen.
</div>
@endif
@endsection
