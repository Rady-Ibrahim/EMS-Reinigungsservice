@extends('admin.layouts.app')
@section('title', 'Zeitkorrekturen')

@section('content')
{{-- Pending Requests --}}
<div class="card" style="margin-bottom:1.5rem">
    <div class="card-header">
        <h1 class="card-title">
            Ausstehende Anträge
            @if($pending->total() > 0)
                <span class="badge badge-red" style="font-size:.8rem;margin-left:.5rem">{{ $pending->total() }}</span>
            @endif
        </h1>
    </div>

    @if($pending->isEmpty())
        <p style="color:#94a3b8;font-size:.875rem;padding:.5rem 0">Keine ausstehenden Anträge. ✓</p>
    @else
    <table>
        <thead>
            <tr>
                <th>Mitarbeiter</th>
                <th>Auftragstyp</th>
                <th>Angeforderter Zeitraum</th>
                <th>Ursprünglicher Zeitraum</th>
                <th>Grund</th>
                <th>Eingereicht</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pending as $req)
            <tr>
                <td><strong>{{ $req->employee->name }}</strong></td>
                <td>
                    <span class="badge badge-gray" style="font-size:.75rem">
                        {{ $req->job_type === 'fix_object' ? 'Fixobjekt' : 'Extra-Auftrag' }}
                    </span>
                </td>
                <td style="font-size:.8rem">
                    {{ $req->requested_start->format('d.m H:i') }} –
                    {{ $req->requested_end->format('H:i') }}
                </td>
                <td style="font-size:.8rem;color:#94a3b8">
                    {{ $req->original_start?->format('d.m H:i') ?? '—' }} –
                    {{ $req->original_end?->format('H:i') ?? '—' }}
                </td>
                <td style="max-width:200px;font-size:.8rem">{{ Str::limit($req->reason, 60) }}</td>
                <td style="font-size:.8rem">{{ $req->client_submitted_at?->format('d.m.Y H:i') ?? $req->created_at->format('d.m.Y H:i') }}</td>
                <td>
                    <div class="actions">
                        <a href="{{ route('admin.time-adjustments.show', $req) }}" class="btn btn-secondary btn-sm">Details</a>

                        {{-- Quick approve --}}
                        <form action="{{ route('admin.time-adjustments.approve', $req) }}" method="POST" style="display:inline">
                            @csrf
                            <button class="btn btn-primary btn-sm" onclick="return confirm('Zeitkorrektur genehmigen?')">✓ Genehmigen</button>
                        </form>

                        {{-- Quick reject --}}
                        <form action="{{ route('admin.time-adjustments.reject', $req) }}" method="POST" style="display:inline"
                              onsubmit="return promptReject(this)">
                            @csrf
                            <input type="hidden" name="admin_note" id="note_{{ $req->id }}">
                            <button type="submit" class="btn btn-danger btn-sm">✗ Ablehnen</button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>

{{-- All Requests --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Alle Anträge</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Mitarbeiter</th>
                <th>Typ</th>
                <th>Status</th>
                <th>Geprüft von</th>
                <th>Datum</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($all as $req)
            <tr>
                <td>{{ $req->employee->name }}</td>
                <td><span class="badge badge-gray" style="font-size:.75rem">{{ $req->job_type === 'fix_object' ? 'Fixobjekt' : 'Extra' }}</span></td>
                <td>
                    @php $s = $req->status->value @endphp
                    <span class="badge {{ $s === 'approved' ? 'badge-green' : ($s === 'rejected' ? 'badge-red' : 'badge-gray') }}">
                        {{ $req->status->label() }}
                    </span>
                </td>
                <td>{{ $req->reviewer?->name ?? '—' }}</td>
                <td style="font-size:.8rem">{{ $req->created_at->format('d.m.Y') }}</td>
                <td><a href="{{ route('admin.time-adjustments.show', $req) }}" class="btn btn-secondary btn-sm">Details</a></td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:1.5rem">Keine Anträge vorhanden.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="pagination">{{ $all->links() }}</div>
</div>

<script>
function promptReject(form) {
    const note = prompt('Ablehnungsgrund (Pflicht):');
    if (!note || note.trim().length < 5) {
        alert('Bitte mindestens 5 Zeichen eingeben.');
        return false;
    }
    form.querySelector('[name="admin_note"]').value = note;
    return true;
}
</script>
@endsection
