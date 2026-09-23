@extends('admin.layouts.app')
@section('title', $extraAuftrag->title)

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h1>{{ $extraAuftrag->title }}
        <span class="badge {{ $extraAuftrag->status->badgeClass() }}" style="font-size:.8rem;margin-left:.5rem">
            {{ $extraAuftrag->status->label() }}
        </span>
    </h1>
    <div class="actions">
        @if(!$extraAuftrag->status->isTerminal())
        <a href="{{ route('admin.extra-auftraege.edit', $extraAuftrag) }}" class="btn btn-secondary">Bearbeiten</a>
        @endif
        @if($extraAuftrag->status->value === 'completed')
        <form method="GET" action="javascript:void(0)" id="reopen-extra-form" style="display:inline;">
            @csrf
            <button type="button" class="btn btn-danger" onclick="document.getElementById('reopen-modal').style.display='block'">Wieder öffnen</button>
        </form>
        @endif
    </div>
</div>

@if($extraAuftrag->status->value === 'completed')
{{-- Reopen confirmation modal --}}
<div id="reopen-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:50;">
    <div style="background:#fff;border-radius:10px;padding:1.5rem;max-width:440px;width:90%;margin:auto;margin-top:10vh;">
        <h2 class="card-title" style="margin-bottom:.75rem">Auftrag wieder öffnen</h2>
        <p style="font-size:.875rem;color:#475569;margin-bottom:1rem;">
            Der Auftrag wird auf <strong>Zugewiesen</strong> zurückgesetzt und alle Ausführungen
            können erneut abgeschlossen werden. Bitte gib eine Begründung an (Pflicht, wird im Audit-Protokoll erfasst).
        </p>
        <form method="POST" action="{{ route('admin.extra-auftraege.reopen', $extraAuftrag) }}">
            @csrf
            <div class="form-group">
                <label for="reopen_reason">Begründung</label>
                <textarea id="reopen_reason" name="reopen_reason" rows="3" required></textarea>
                @error('reopen_reason')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-danger">Bestätigen</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('reopen-modal').style.display='none'">Abbrechen</button>
            </div>
        </form>
    </div>
</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
    {{-- Order Details --}}
    <div class="card">
        <div class="card-title" style="margin-bottom:.75rem">Auftragsdaten</div>
        <table>
            <tr><td style="color:#64748b;width:150px">Kunde</td><td>{{ $extraAuftrag->customer->name }}</td></tr>
            <tr><td style="color:#64748b">Standort</td><td>{{ $extraAuftrag->location->name }}</td></tr>
            <tr><td style="color:#64748b">Typ</td><td>{{ $extraAuftrag->order_type->label() }}</td></tr>
            <tr><td style="color:#64748b">Datum</td><td>{{ $extraAuftrag->scheduled_date->format('d.m.Y') }}
                @if($extraAuftrag->scheduled_time_start) um {{ substr($extraAuftrag->scheduled_time_start,0,5) }} Uhr @endif
            </td></tr>
            <tr><td style="color:#64748b">Gesch. Std.</td><td>{{ $extraAuftrag->estimated_hours ?? '—' }}h</td></tr>
            <tr><td style="color:#64748b">Anfahrt bezahlt</td><td>
                @if($extraAuftrag->is_travel_time_paid)
                    <span class="badge badge-green">Ja</span>
                @else
                    <span class="badge badge-gray">Nein</span>
                @endif
            </td></tr>
            <tr><td style="color:#64748b">Erstellt von</td><td>{{ $extraAuftrag->creator->name }}</td></tr>
        </table>
        @if($extraAuftrag->description)
        <div style="margin-top:.75rem;padding:.65rem;background:#f8fafc;border-radius:6px;font-size:.85rem">
            {{ $extraAuftrag->description }}
        </div>
        @endif
        @if($extraAuftrag->price)
        <div style="margin-top:.75rem;padding:.65rem;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;font-size:.85rem">
            💰 Preis: <strong>{{ number_format((float)$extraAuftrag->price, 2) }} €</strong>
            @if($extraAuftrag->internal_cost)
            | Kosten: {{ number_format((float)$extraAuftrag->internal_cost, 2) }} €
            @endif
        </div>
        @endif
    </div>

    {{-- Team --}}
    <div class="card">
        <div class="card-title" style="margin-bottom:.75rem">Team ({{ $extraAuftrag->assignees->count() }})</div>
        @forelse($extraAuftrag->assignees as $assignee)
        <div style="padding:.5rem 0;border-bottom:1px solid #f1f5f9">
            <span class="color-dot" style="background:{{ $assignee->user->employeeProfile?->calendar_color ?? '#ccc' }}"></span>
            <strong>{{ $assignee->user->name }}</strong>
            @if($assignee->isLeader())
                <span class="badge badge-blue" style="font-size:.7rem;margin-left:.35rem">Vorarbeiter</span>
            @endif
            {{-- Execution summary --}}
            @php $exec = $extraAuftrag->executions->firstWhere('user_id', $assignee->user_id) @endphp
            @if($exec)
            <small style="color:#64748b;display:block;margin-left:20px">
                Status: {{ $exec->status->label() }}
                @if($exec->paid_minutes) | Bezahlt: {{ round($exec->paid_minutes/60, 2) }}h @endif
            </small>
            @endif
        </div>
        @empty
        <p style="color:#94a3b8;font-size:.85rem">Keine Mitarbeiter zugewiesen.</p>
        @endforelse
    </div>
</div>

{{-- Checklist Template --}}
@if($extraAuftrag->checklist_template)
<div class="card">
    <div class="card-title" style="margin-bottom:.75rem">Checkliste ({{ count($extraAuftrag->checklist_template) }} Punkte)</div>
    <ul style="list-style:none;padding:0">
        @foreach($extraAuftrag->checklist_template as $item)
        <li style="padding:.35rem 0;border-bottom:1px solid #f1f5f9;font-size:.875rem">
            <span style="margin-right:.5rem">☐</span> {{ $item['task'] }}
        </li>
        @endforeach
    </ul>
</div>
@endif

{{-- Travel Tracks --}}
@if($extraAuftrag->travelTracks->count())
<div class="card">
    <div class="card-title" style="margin-bottom:.75rem">Anfahrten</div>
    <table>
        <thead><tr><th>Mitarbeiter</th><th>Abfahrt</th><th>Ankunft</th><th>Fahrzeit</th><th>Bezahlt</th></tr></thead>
        <tbody>
            @foreach($extraAuftrag->travelTracks as $track)
            <tr>
                <td>{{ $track->employee->name }}</td>
                <td>{{ $track->departure_at->format('H:i') }}</td>
                <td>{{ $track->arrival_at?->format('H:i') ?? '—' }}</td>
                <td>{{ $track->travel_minutes ? $track->travel_minutes . ' Min.' : '—' }}</td>
                <td>
                    @if($track->is_paid)
                        <span class="badge badge-green">Ja</span>
                    @else
                        <span class="badge badge-gray">Nein</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
