@extends('admin.layouts.app')
@section('title', 'Zeitkorrektur-Antrag')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h1>Zeitkorrektur-Antrag #{{ $timeAdjustment->id }}</h1>
    <a href="{{ route('admin.time-adjustments.index') }}" class="btn btn-secondary">← Zurück</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
    <div class="card">
        <div class="card-title" style="margin-bottom:.75rem">Antragsdaten</div>
        <table>
            <tr><td style="color:#64748b;width:160px">Mitarbeiter</td><td><strong>{{ $timeAdjustment->employee->name }}</strong></td></tr>
            <tr><td style="color:#64748b">Auftragstyp</td>
                <td><span class="badge badge-gray">{{ $timeAdjustment->job_type === 'fix_object' ? 'Fixobjekt' : 'Extra-Auftrag' }}</span></td>
            </tr>
            <tr><td style="color:#64748b">Auftrags-ID</td><td>#{{ $timeAdjustment->job_id }}</td></tr>
            <tr><td style="color:#64748b">Status</td>
                <td>
                    @php $s = $timeAdjustment->status->value @endphp
                    <span class="badge {{ $s === 'approved' ? 'badge-green' : ($s === 'rejected' ? 'badge-red' : 'badge-gray') }}">
                        {{ $timeAdjustment->status->label() }}
                    </span>
                </td>
            </tr>
            <tr><td style="color:#64748b">Eingereicht</td>
                <td>{{ $timeAdjustment->client_submitted_at?->format('d.m.Y H:i') ?? $timeAdjustment->created_at->format('d.m.Y H:i') }}</td>
            </tr>
        </table>
    </div>

    <div class="card">
        <div class="card-title" style="margin-bottom:.75rem">Zeitvergleich</div>
        <table>
            <tr>
                <th style="text-align:left;font-weight:600;color:#374151;padding-bottom:.5rem">Original (unveränderlich)</th>
                <th style="text-align:left;font-weight:600;color:#374151;padding-bottom:.5rem">Angefordert</th>
            </tr>
            <tr>
                <td style="color:#94a3b8;font-size:.875rem">
                    Start: {{ $timeAdjustment->original_start?->format('d.m.Y H:i') ?? '—' }}<br>
                    Ende: {{ $timeAdjustment->original_end?->format('H:i') ?? '—' }}
                </td>
                <td style="font-size:.875rem">
                    Start: <strong>{{ $timeAdjustment->requested_start->format('d.m.Y H:i') }}</strong><br>
                    Ende: <strong>{{ $timeAdjustment->requested_end->format('H:i') }}</strong>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="card" style="margin-top:1rem">
    <div class="card-title" style="margin-bottom:.75rem">Begründung</div>
    <p style="font-size:.9rem;line-height:1.6">{{ $timeAdjustment->reason }}</p>
</div>

@if($timeAdjustment->admin_note)
<div class="card" style="margin-top:1rem">
    <div class="card-title" style="margin-bottom:.5rem">Admin-Notiz</div>
    <p style="font-size:.875rem;color:#475569">{{ $timeAdjustment->admin_note }}</p>
    @if($timeAdjustment->reviewer)
    <small style="color:#94a3b8">Bearbeitet von: {{ $timeAdjustment->reviewer->name }} am {{ $timeAdjustment->reviewed_at?->format('d.m.Y H:i') }}</small>
    @endif
</div>
@endif

@if($timeAdjustment->isPending())
<div class="card" style="margin-top:1rem;border:1px solid #e2e8f0">
    <div class="card-title" style="margin-bottom:.75rem">Entscheidung</div>
    <div style="display:flex;gap:1rem;flex-wrap:wrap">
        {{-- Approve --}}
        <form action="{{ route('admin.time-adjustments.approve', $timeAdjustment) }}" method="POST" style="flex:1">
            @csrf
            <div class="form-group">
                <label>Notiz (optional)</label>
                <textarea name="admin_note" rows="2" placeholder="Optionale Notiz..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary" onclick="return confirm('Zeitkorrektur genehmigen und anwenden?')">
                ✓ Genehmigen & Anwenden
            </button>
        </form>

        {{-- Reject --}}
        <form action="{{ route('admin.time-adjustments.reject', $timeAdjustment) }}" method="POST" style="flex:1">
            @csrf
            <div class="form-group">
                <label>Ablehnungsgrund *</label>
                <textarea name="admin_note" rows="2" required placeholder="Pflichtfeld bei Ablehnung..."></textarea>
                @error('admin_note')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn btn-danger">✗ Ablehnen</button>
        </form>
    </div>
</div>
@endif
@endsection
