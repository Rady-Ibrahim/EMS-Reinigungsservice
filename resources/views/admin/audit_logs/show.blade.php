@extends('admin.layouts.app')

@section('title', __('audit_log.entry_title'))
@section('content')
    <div class="card-header">
        <h1>{{ __('audit_log.entry_title') }} #{{ $log->id }}</h1>
        <a href="{{ route('admin.audit-log.index') }}" class="btn btn-secondary btn-sm">{{ __('audit_log.back') }}</a>
    </div>

    <div class="card">
        <table>
            <tbody>
                <tr><th style="width:160px;">{{ __('audit_log.event') }}</th><td><span class="badge badge-blue">{{ $log->event->label() }}</span></td></tr>
                <tr><th>{{ __('audit_log.actor') }}</th><td>{{ $log->user?->name ?? '—' }} (ID {{ $log->user_id ?? '—' }})</td></tr>
                <tr><th>{{ __('audit_log.target') }}</th><td><code>{{ $log->auditable_type }} #{{ $log->auditable_id }}</code></td></tr>
                <tr><th>{{ __('audit_log.reason') }}</th><td>{{ $log->reason ?? '—' }}</td></tr>
                <tr><th>{{ __('audit_log.date') }}</th><td>{{ $log->created_at->format('d.m.Y H:i:s') }}</td></tr>
                <tr><th>URL</th><td><code>{{ $log->url ?? '—' }}</code></td></tr>
                <tr><th>{{ __('audit_log.ip') }}</th><td>{{ $log->ip_address ?? '—' }}</td></tr>
                <tr><th>{{ __('audit_log.user_agent') }}</th><td><code>{{ $log->user_agent ?? '—' }}</code></td></tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="form-row">
            <div>
                <h3 style="font-size:.95rem;margin-bottom:.5rem;">{{ __('audit_log.old_values') }}</h3>
                <pre style="background:#f8fafc;padding:.75rem;border-radius:8px;font-size:.8rem;overflow:auto;">{{ json_encode($log->old_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
            <div>
                <h3 style="font-size:.95rem;margin-bottom:.5rem;">{{ __('audit_log.new_values') }}</h3>
                <pre style="background:#f8fafc;padding:.75rem;border-radius:8px;font-size:.8rem;overflow:auto;">{{ json_encode($log->new_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
    </div>
@endsection