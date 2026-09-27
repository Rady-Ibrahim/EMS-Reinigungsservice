@extends('admin.layouts.app')

@section('title', __('audit_log.title'))
@section('content')
    <div class="card-header">
        <h1>{{ __('audit_log.title') }}</h1>
    </div>

    <form method="GET" action="{{ route('admin.audit-log.index') }}" style="margin-bottom:.75rem;">
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
            <select name="event" style="width:auto;">
                <option value="">{{ __('audit_log.all_events') }}</option>
                @foreach($events as $event)
                    <option value="{{ $event->value }}" {{ request('event') === $event->value ? 'selected' : '' }}>{{ $event->label() }}</option>
                @endforeach
            </select>
            <input type="number" name="actor" placeholder="{{ __('audit_log.actor_id') }}" value="{{ request('actor') }}" style="width:110px;">
            <input type="text" name="target_type" placeholder="{{ __('audit_log.target_class') }}" value="{{ request('target_type') }}" style="width:180px;">
            <input type="date" name="from" value="{{ request('from') }}" style="width:auto;">
            <input type="date" name="to" value="{{ request('to') }}" style="width:auto;">
            <button type="submit" class="btn btn-secondary btn-sm">{{ __('audit_log.filter') }}</button>
            <a href="{{ route('admin.audit-log.index') }}" class="btn btn-secondary btn-sm">{{ __('audit_log.reset') }}</a>
        </div>
    </form>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>{{ __('audit_log.event') }}</th>
                    <th>{{ __('audit_log.actor') }}</th>
                    <th>{{ __('audit_log.target') }}</th>
                    <th>{{ __('audit_log.reason') }}</th>
                    <th>{{ __('audit_log.date') }}</th>
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
                    <tr><td colspan="6">{{ __('audit_log.no_entries') }}</td></tr>
                @endforelse
            </tbody>
        </table>

        {{ $logs->withQueryString()->links() }}
    </div>
@endsection