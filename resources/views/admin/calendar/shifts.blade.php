@extends('admin.layouts.app')

@section('title', 'Schichten')
@section('content')
    <div class="card-header">
        <h1>Schichten</h1>
        <a href="{{ route('admin.calendar.shifts.create') }}" class="btn btn-primary">+ Neue Schicht</a>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Mitarbeiter</th>
                    <th>Titel</th>
                    <th>Beginn</th>
                    <th>Ende</th>
                    <th>Ganztägig</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                @forelse($shifts as $shift)
                    <tr>
                        <td>{{ $shift->user?->name }}</td>
                        <td>
                            <span class="color-dot" style="background:{{ $shift->color ?? '#10b981' }}"></span>
                            {{ $shift->title }}
                        </td>
                        <td>{{ $shift->start_at->format('d.m.Y H:i') }}</td>
                        <td>{{ $shift->end_at->format('d.m.Y H:i') }}</td>
                        <td>{{ $shift->all_day ? 'Ja' : 'Nein' }}</td>
                        <td>
                            @if($shift->status->value === 'cancelled')
                                <span class="badge badge-red">{{ $shift->status->label() }}</span>
                            @else
                                <span class="badge badge-green">{{ $shift->status->label() }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('admin.calendar.shifts.edit', $shift) }}" class="btn btn-secondary btn-sm">Edit</a>
                                @if($shift->status->value !== 'cancelled')
                                    <form action="{{ route('admin.calendar.shifts.cancel', $shift) }}" method="POST" onsubmit="return confirm('Schicht stornieren?');">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-sm">Stornieren</button>
                                    </form>
                                @endif
                                <form action="{{ route('admin.calendar.shifts.destroy', $shift) }}" method="POST" onsubmit="return confirm('Wirklich löschen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Keine Schichten vorhanden.</td></tr>
                @endforelse
            </tbody>
        </table>

        {{ $shifts->links() }}
    </div>
@endsection