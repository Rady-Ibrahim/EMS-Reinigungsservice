@extends('admin.layouts.app')

@section('title', 'Interne Termine')
@section('content')
    <div class="card-header">
        <h1>Interne Termine</h1>
        <a href="{{ route('admin.calendar.internal-events.create') }}" class="btn btn-primary">+ Neuer Termin</a>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Titel</th>
                    <th>Art</th>
                    <th>Beginn</th>
                    <th>Ende</th>
                    <th>Teilnehmer</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                @forelse($events as $event)
                    <tr>
                        <td>
                            <span class="color-dot" style="background:{{ $event->color ?? '#f59e0b' }}"></span>
                            {{ $event->title }}
                        </td>
                        <td><span class="badge badge-yellow">{{ $event->event_type->label() }}</span></td>
                        <td>{{ $event->all_day ? $event->start_at->format('d.m.Y') : $event->start_at->format('d.m.Y H:i') }}</td>
                        <td>{{ $event->all_day ? $event->end_at->format('d.m.Y') : $event->end_at->format('d.m.Y H:i') }}</td>
                        <td style="font-size:.8rem;">
                            {{ $event->assignees->map(fn($a) => $a->user?->name)->filter()->join(', ') ?: '—' }}
                        </td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('admin.calendar.internal-events.edit', $event) }}" class="btn btn-secondary btn-sm">Edit</a>
                                <form action="{{ route('admin.calendar.internal-events.destroy', $event) }}" method="POST" onsubmit="return confirm('Termin löschen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Keine internen Termine vorhanden.</td></tr>
                @endforelse
            </tbody>
        </table>

        {{ $events->links() }}
    </div>
@endsection