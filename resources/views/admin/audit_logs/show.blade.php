@extends('admin.layouts.app')

@section('title', 'Audit-Eintrag')
@section('content')
    <div class="card-header">
        <h1>Audit-Eintrag #{{ $log->id }}</h1>
        <a href="{{ route('admin.audit-log.index') }}" class="btn btn-secondary btn-sm">Zurück</a>
    </div>

    <div class="card">
        <table>
            <tbody>
                <tr><th style="width:160px;">Ereignis</th><td><span class="badge badge-blue">{{ $log->event->label() }}</span></td></tr>
                <tr><th>Akteur</th><td>{{ $log->user?->name ?? '—' }} (ID {{ $log->user_id ?? '—' }})</td></tr>
                <tr><th>Ziel</th><td><code>{{ $log->auditable_type }} #{{ $log->auditable_id }}</code></td></tr>
                <tr><th>Begründung</th><td>{{ $log->reason ?? '—' }}</td></tr>
                <tr><th>Datum</th><td>{{ $log->created_at->format('d.m.Y H:i:s') }}</td></tr>
                <tr><th>URL</th><td><code>{{ $log->url ?? '—' }}</code></td></tr>
                <tr><th>IP-Adresse</th><td>{{ $log->ip_address ?? '—' }}</td></tr>
                <tr><th>User-Agent</th><td><code>{{ $log->user_agent ?? '—' }}</code></td></tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="form-row">
            <div>
                <h3 style="font-size:.95rem;margin-bottom:.5rem;">Vorherige Werte</h3>
                <pre style="background:#f8fafc;padding:.75rem;border-radius:8px;font-size:.8rem;overflow:auto;">{{ json_encode($log->old_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
            <div>
                <h3 style="font-size:.95rem;margin-bottom:.5rem;">Neue Werte</h3>
                <pre style="background:#f8fafc;padding:.75rem;border-radius:8px;font-size:.8rem;overflow:auto;">{{ json_encode($log->new_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
    </div>
@endsection