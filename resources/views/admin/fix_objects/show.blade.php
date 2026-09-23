@extends('admin.layouts.app')
@section('title', $fixObject->title)

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h1>
        <span style="display:inline-block;width:16px;height:16px;border-radius:50%;background:{{ $fixObject->calendar_color }};border:1px solid rgba(0,0,0,.2);vertical-align:middle;margin-right:8px"></span>
        {{ $fixObject->title }}
        @if(!$fixObject->is_active)<span class="badge badge-red" style="font-size:.75rem;margin-left:.5rem">Inaktiv</span>@endif
    </h1>
    <a href="{{ route('admin.fix-objects.edit', $fixObject) }}" class="btn btn-secondary">Bearbeiten</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
    {{-- Contract Info --}}
    <div class="card">
        <div class="card-title" style="margin-bottom:.75rem">Vertragsdaten</div>
        <table>
            <tr><td style="color:#64748b;width:140px">Kunde</td><td>{{ $fixObject->customer->name }}</td></tr>
            <tr><td style="color:#64748b">Standort</td><td>{{ $fixObject->location->name }}</td></tr>
            <tr><td style="color:#64748b">Frequenz</td><td>{{ $fixObject->frequency->label() }}</td></tr>
            <tr><td style="color:#64748b">Wochentage</td><td>{{ implode(', ', $fixObject->frequency_days ?? []) ?: '—' }}</td></tr>
            <tr><td style="color:#64748b">Zeiten</td>
                <td>{{ $fixObject->time_start ? substr($fixObject->time_start, 0, 5) : '—' }} – {{ $fixObject->time_end ? substr($fixObject->time_end, 0, 5) : '—' }}</td>
            </tr>
            <tr><td style="color:#64748b">Vertragsstd.</td><td><strong>{{ $fixObject->contract_hours }}h</strong></td></tr>
            <tr><td style="color:#64748b">Akt. Monat</td><td><strong style="color:#1e40af">{{ $contractHours }}h</strong></td></tr>
            <tr><td style="color:#64748b">Gültigkeit</td>
                <td>{{ $fixObject->valid_from->format('d.m.Y') }} – {{ $fixObject->valid_until?->format('d.m.Y') ?? '∞' }}</td>
            </tr>
        </table>
        @if($fixObject->price_per_month || $fixObject->internal_cost)
        <div style="margin-top:1rem;padding:.75rem;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;font-size:.85rem">
            <strong>💰 Finanzdaten</strong><br>
            @if($fixObject->price_per_month) Preis/Monat: <strong>{{ number_format((float)$fixObject->price_per_month, 2) }} €</strong><br>@endif
            @if($fixObject->internal_cost) Int. Kosten: {{ number_format((float)$fixObject->internal_cost, 2) }} €<br>@endif
            @if($fixObject->profit_margin) Marge: {{ $fixObject->profit_margin }} €@endif
        </div>
        @endif
    </div>

    {{-- Assignments --}}
    <div class="card">
        <div class="card-header" style="margin-bottom:.75rem">
            <span class="card-title">Mitarbeiter ({{ $fixObject->assignments->count() }})</span>
        </div>
        @forelse($fixObject->assignments as $assignment)
        <div style="padding:.5rem 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center">
            <div>
                <span class="color-dot" style="background:{{ $assignment->user->employeeProfile?->calendar_color ?? '#ccc' }}"></span>
                <strong>{{ $assignment->user->name }}</strong>
                <small style="color:#64748b;display:block;margin-left:20px">
                    ab {{ $assignment->assigned_from->format('d.m.Y') }}
                    {{ $assignment->assigned_until ? '– '.$assignment->assigned_until->format('d.m.Y') : '' }}
                </small>
            </div>
            <form action="{{ route('admin.fix-objects.assignments.destroy', [$fixObject, $assignment]) }}" method="POST">
                @csrf @method('DELETE')
                <button class="btn btn-danger btn-sm">Entfernen</button>
            </form>
        </div>
        @empty
        <p style="color:#94a3b8;font-size:.85rem">Keine Mitarbeiter zugewiesen.</p>
        @endforelse

        {{-- Add assignment --}}
        <details style="margin-top:.75rem">
            <summary style="cursor:pointer;font-size:.85rem;color:#1e40af">+ Mitarbeiter zuweisen</summary>
            <form action="{{ route('admin.fix-objects.assignments.store', $fixObject) }}" method="POST" style="margin-top:.5rem">
                @csrf
                <select name="user_id" style="width:100%;margin-bottom:.5rem;padding:.4rem">
                    @foreach(\App\Models\User::whereIn('role',['vorarbeiter','mitarbeiter'])->orderBy('name')->get() as $emp)
                    <option value="{{ $emp->id }}">{{ $emp->name }} ({{ $emp->role->label() }})</option>
                    @endforeach
                </select>
                <div style="display:flex;gap:.5rem">
                    <input type="date" name="assigned_from" value="{{ now()->toDateString() }}" style="flex:1;padding:.35rem">
                    <input type="date" name="assigned_until" style="flex:1;padding:.35rem" placeholder="bis (leer=offen)">
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:.5rem">Zuweisen</button>
            </form>
        </details>
    </div>
</div>

{{-- Schedules this month --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Termine dieses Monats ({{ now()->format('F Y') }})</span>
        <form action="{{ route('admin.fix-objects.generate-schedules', $fixObject) }}" method="POST" style="display:flex;gap:.5rem">
            @csrf
            <input type="hidden" name="year"  value="{{ now()->year }}">
            <input type="hidden" name="month" value="{{ now()->month }}">
            <button class="btn btn-secondary btn-sm">Termine neu generieren</button>
        </form>
    </div>
    <table>
        <thead><tr><th>Datum</th><th>Start</th><th>Ende</th><th>Status</th><th>Ausführungen</th></tr></thead>
        <tbody>
            @forelse($schedules as $schedule)
            <tr>
                <td>{{ $schedule->scheduled_date->format('d.m.Y (D)') }}</td>
                <td>{{ $schedule->scheduled_start ? substr($schedule->scheduled_start, 0, 5) : '—' }}</td>
                <td>{{ $schedule->scheduled_end   ? substr($schedule->scheduled_end, 0, 5)   : '—' }}</td>
                <td>
                    @php $s = $schedule->status->value; @endphp
                    <span class="badge {{ $s === 'completed' ? 'badge-green' : ($s === 'in_progress' ? 'badge-gray' : 'badge-gray') }}">
                        {{ $schedule->status->label() }}
                    </span>
                </td>
                <td>{{ $schedule->executions->count() }} Ausf.</td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;color:#94a3b8">Keine Termine generiert.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
