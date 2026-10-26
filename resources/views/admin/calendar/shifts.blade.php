@extends('admin.layouts.app')

@section('title', __('cal_page.shifts'))
@section('content')
    <div class="card-header">
        <h1>{{ __('cal_page.shifts') }}</h1>
        <a href="{{ route('admin.calendar.shifts.create') }}" class="btn btn-primary">+ {{ __('cal_page.new_shift') }}</a>
    </div>

    <div class="card">
        <table class="mobile-card-table">
            <thead>
                <tr>
                    <th>{{ __('cal_page.employee') }}</th>
                    <th>{{ __('cal_page.title') }}</th>
                    <th>{{ __('cal_page.start') }}</th>
                    <th>{{ __('cal_page.end') }}</th>
                    <th>{{ __('cal_page.work_hours') }}</th>
                    <th>{{ __('cal_page.all_day') }}</th>
                    <th>{{ __('cal_page.status') }}</th>
                    <th>{{ __('cal_page.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($shifts as $shift)
                    <tr>
                        <td data-label="{{ __('cal_page.employee') }}">{{ $shift->user?->name ?? '—' }}</td>
                        <td data-label="{{ __('cal_page.title') }}">
                            <span class="color-dot" style="background:{{ $shift->color ?? '#10b981' }}"></span>
                            {{ $shift->title }}
                        </td>
                        <td data-label="{{ __('cal_page.start') }}">{{ $shift->start_at->format('d.m.Y H:i') }}</td>
                        <td data-label="{{ __('cal_page.end') }}">{{ $shift->end_at->format('d.m.Y H:i') }}</td>
                        <td data-label="{{ __('cal_page.work_hours') }}">{{ number_format($shift->scheduledWorkHours(), 2, ',', '.') }} h</td>
                        <td data-label="{{ __('cal_page.all_day') }}">{{ $shift->all_day ? __('cal_page.yes') : __('cal_page.no') }}</td>
                        <td data-label="{{ __('cal_page.status') }}">
                            @if($shift->status->value === 'cancelled')
                                <span class="badge badge-red">{{ $shift->status->label() }}</span>
                            @elseif($shift->status->value === 'completed')
                                <span class="badge badge-green">{{ $shift->status->label() }}</span>
                            @else
                                <span class="badge badge-green">{{ $shift->status->label() }}</span>
                            @endif
                        </td>
                        <td data-label="{{ __('cal_page.actions') }}">
                            <div class="actions">
                                <a href="{{ route('admin.calendar.shifts.edit', $shift) }}" class="btn btn-secondary btn-sm">{{ __('cal_page.edit') }}</a>
                                @if($shift->status->value !== 'cancelled')
                                    @if($shift->status->value !== 'completed')
                                        <form action="{{ route('admin.calendar.shifts.complete', $shift) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm">{{ __('cal_page.mark_complete') }}</button>
                                        </form>
                                    @endif
                                    <form action="{{ route('admin.calendar.shifts.cancel', $shift) }}" method="POST" onsubmit="return confirm('{{ __('cal_page.confirm_cancel_shift') }}');">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-sm">{{ __('cal_page.cancel_shift') }}</button>
                                    </form>
                                @endif
                                <form action="{{ route('admin.calendar.shifts.destroy', $shift) }}" method="POST" onsubmit="return confirm('{{ __('cal_page.confirm_delete') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">{{ __('cal_page.delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="mobile-table-empty"><td colspan="8">{{ __('cal_page.empty_shifts') }}</td></tr>
                @endforelse
            </tbody>
        </table>

        {{ $shifts->links() }}
    </div>
@endsection
