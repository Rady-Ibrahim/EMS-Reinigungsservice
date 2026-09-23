@extends('admin.layouts.app')

@section('title', 'Benachrichtigungen')
@section('content')
    <div class="card-header">
        <h1>Benachrichtigungen</h1>
        <div class="actions">
            <form action="{{ route('admin.notifications.read-all') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm">Alle als gelesen</button>
            </form>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.notifications.index') }}" style="margin-bottom:.75rem;">
        <div style="display:flex;gap:.5rem;align-items:center;">
            <select name="type" style="width:auto;">
                <option value="">Alle Typen</option>
                @foreach($types as $type)
                    <option value="{{ $type->value }}" {{ request('type') === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Filtern</button>
        </div>
    </form>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Typ</th>
                    <th>Titel</th>
                    <th>Meldung</th>
                    <th>Datum</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($notifications as $notification)
                    <tr style="{{ $notification->is_read ? 'opacity:.65;' : '' }}">
                        <td><span class="badge {{ $notification->type->badgeClass() }}">{{ $notification->type->label() }}</span></td>
                        <td>
                            <a href="{{ route('admin.notifications.show', $notification) }}">{{ $notification->title }}</a>
                        </td>
                        <td style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $notification->message }}</td>
                        <td>{{ $notification->created_at->format('d.m.Y H:i') }}</td>
                        <td>
                            @if(! $notification->is_read)
                                <form action="{{ route('admin.notifications.read', $notification) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm">Als gelesen</button>
                                </form>
                            @else
                                <span class="badge badge-gray">gelesen</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Keine Benachrichtigungen.</td></tr>
                @endforelse
            </tbody>
        </table>

        {{ $notifications->withQueryString()->links() }}
    </div>
@endsection