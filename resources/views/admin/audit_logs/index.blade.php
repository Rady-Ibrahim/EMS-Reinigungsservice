@extends('admin.layouts.app')

@section('title', 'Audit-Protokoll')
@section('content')
    <div class="card-header">
        <h1>Audit-Protokoll</h1>
    </div>

    <form method="GET" action="{{ route('admin.audit-log.index') }}" style="margin-bottom:.75rem;">
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
            <select name="event" style="width:auto;">
                <option value="">Alle Ereignisse</option>
                @foreach($events as $event)
                    <option value="{{ $event->value }}" {{ request('event') === $event->value ? 'selected' : '' }}>{{ $event->label() }}</option>
                @endforeach
            </select>
            <input type="number" name="actor" placeholder="Akteur-ID" value="{{ request('actor') }}" style="width:110px;">
            <input type="text" name="target_type" placeholder="Zielklasse" value="{{ request('target_type') }}" style="width:180px;">
            <input type="date" name="from" value="{{ request('from') }}" style="width:auto;">
            <input type="date" name="to" value="{{ request('to') }}" style="width:auto;">
            <button type="submit" class="btn btn-secondary btn-sm">Filtern</button>
            <a href="{{ route('admin.audit-log.index') }}" class="btn btn-secondary btn-sm">Zurücksetzen</a>
        </div>
    </form>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Ereignis</th>
                    <th>Akteur</th>
                    <th>Ziel</th>
                    <th>Begründung</th>
                    <th>Datum</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>
                            <a href="{{ route('admin.audit-log.show', $log) }}">
                                <span class="badge badge-blue">{{ $log->event->label() }}</span>
                            </a>
                        </td>
                        <td>{{ $log->user?->name ?? '—' }}</td>
                        <td><code>{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</code>
                            @if($log->auditable && filled($log->auditable->name ?? $log->auditable->title ?? null))
                                <div style="color:#475569;font-size:.8rem;margin-top:.15rem;">{{ $log->auditable->name ?? $log->auditable->title }}</div>
                            @endif
                        </td>
                        <td style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $log->reason ?? '—' }}</td>
                        <td>{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                        <td>{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">Keine Einträge gefunden.</td></tr>
                @endforelse
            </tbody>
        </table>

        {{ $logs->withQueryString()->links() }}
    </div>
@endsection