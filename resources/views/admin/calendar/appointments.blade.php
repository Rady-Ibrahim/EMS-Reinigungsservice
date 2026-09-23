@extends('admin.layouts.app')

@section('title', 'Meine Termine')
@section('content')
    <div class="card-header">
        <h1>Persönliche Termine</h1>
        <a href="{{ route('admin.calendar.appointments.create') }}" class="btn btn-primary">+ Neuer Termin</a>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Benutzer</th>
                    <th>Titel</th>
                    <th>Beginn</th>
                    <th>Ende</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                @forelse($appointments as $appointment)
                    <tr>
                        <td>{{ $appointment->user?->name }}</td>
                        <td>
                            <span class="color-dot" style="background:{{ $appointment->color ?? '#8b5cf6' }}"></span>
                            {{ $appointment->title }}
                        </td>
                        <td>{{ $appointment->all_day ? $appointment->start_at->format('d.m.Y') : $appointment->start_at->format('d.m.Y H:i') }}</td>
                        <td>{{ $appointment->all_day ? $appointment->end_at->format('d.m.Y') : $appointment->end_at->format('d.m.Y H:i') }}</td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('admin.calendar.appointments.edit', $appointment) }}" class="btn btn-secondary btn-sm">Edit</a>
                                <form action="{{ route('admin.calendar.appointments.destroy', $appointment) }}" method="POST" onsubmit="return confirm('Termin löschen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Keine persönlichen Termine vorhanden.</td></tr>
                @endforelse
            </tbody>
        </table>

        {{ $appointments->links() }}
    </div>
@endsection