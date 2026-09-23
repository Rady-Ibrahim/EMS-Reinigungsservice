@extends('admin.layouts.app')
@section('title', 'Fixobjekte')

@section('content')
<div class="card">
    <div class="card-header">
        <h1 class="card-title">Fixobjekte</h1>
        <a href="{{ route('admin.fix-objects.create') }}" class="btn btn-primary">+ Neues Fixobjekt</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Farbe</th>
                <th>Titel</th>
                <th>Kunde / Standort</th>
                <th>Frequenz</th>
                <th>Vertragsstd.</th>
                <th>Mitarbeiter</th>
                <th>Status</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            @forelse($fixObjects as $fo)
            <tr>
                <td>
                    <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:{{ $fo->calendar_color }};border:1px solid rgba(0,0,0,.15)"></span>
                </td>
                <td>
                    <a href="{{ route('admin.fix-objects.show', $fo) }}"><strong>{{ $fo->title }}</strong></a>
                </td>
                <td>
                    <small style="color:#64748b">{{ $fo->customer->name ?? '—' }}</small><br>
                    <small>{{ $fo->location->name ?? '—' }}</small>
                </td>
                <td><span class="badge badge-gray">{{ $fo->frequency->label() }}</span></td>
                <td>{{ $fo->contract_hours }}h</td>
                <td>{{ $fo->assignments_count }}</td>
                <td>
                    @if($fo->is_active)
                        <span class="badge badge-green">Aktiv</span>
                    @else
                        <span class="badge badge-red">Inaktiv</span>
                    @endif
                </td>
                <td>
                    <div class="actions">
                        <a href="{{ route('admin.fix-objects.edit', $fo) }}" class="btn btn-secondary btn-sm">Bearbeiten</a>
                        <form action="{{ route('admin.fix-objects.destroy', $fo) }}" method="POST"
                              onsubmit="return confirm('Wirklich löschen?')" style="display:inline">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger btn-sm">Löschen</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:2rem">Keine Fixobjekte gefunden.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">{{ $fixObjects->links() }}</div>
</div>
@endsection
