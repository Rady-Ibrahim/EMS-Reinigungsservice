@extends('admin.layouts.app')

@section('title', __('cal_page.internal_events'))
@section('content')
    <div class="card-header">
        <h1>{{ __('cal_page.internal_events') }}</h1>
        <a href="{{ route('admin.calendar.internal-events.create') }}" class="btn btn-primary">+ {{ __('cal_page.new_appointment') }}</a>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>{{ __('cal_page.title') }}</th>
                    <th>{{ __('cal_page.type') }}</th>
                    <th>{{ __('cal_page.start') }}</th>
                    <th>{{ __('cal_page.end') }}</th>
                    <th>{{ __('cal_page.participants') }}</th>
                    <th>{{ __('cal_page.actions') }}</th>
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
                                <a href="{{ route('admin.calendar.internal-events.edit', $event) }}" class="btn btn-secondary btn-sm">{{ __('cal_page.edit') }}</a>
                                <form action="{{ route('admin.calendar.internal-events.destroy', $event) }}" method="POST" onsubmit="return confirm('{{ __('cal_page.confirm_delete_appointment') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">{{ __('cal_page.delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">{{ __('cal_page.empty_internal_events') }}</td></tr>
                @endforelse
            </tbody>
        </table>

        {{ $events->links() }}
    </div>
@endsection
