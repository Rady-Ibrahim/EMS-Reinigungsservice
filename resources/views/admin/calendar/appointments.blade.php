@extends('admin.layouts.app')

@section('title', __('cal_page.my_appointments'))
@section('content')
    <div class="card-header">
        <h1>{{ __('cal_page.personal_appointments') }}</h1>
        <a href="{{ route('admin.calendar.appointments.create') }}" class="btn btn-primary">+ {{ __('cal_page.new_appointment') }}</a>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>{{ __('cal_page.user') }}</th>
                    <th>{{ __('cal_page.title') }}</th>
                    <th>{{ __('cal_page.start') }}</th>
                    <th>{{ __('cal_page.end') }}</th>
                    <th>{{ __('cal_page.actions') }}</th>
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
                                <a href="{{ route('admin.calendar.appointments.edit', $appointment) }}" class="btn btn-secondary btn-sm">{{ __('cal_page.edit') }}</a>
                                <form action="{{ route('admin.calendar.appointments.destroy', $appointment) }}" method="POST" onsubmit="return confirm('{{ __('cal_page.confirm_delete_appointment') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">{{ __('cal_page.delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">{{ __('cal_page.empty_personal_appointments') }}</td></tr>
                @endforelse
            </tbody>
        </table>

        {{ $appointments->links() }}
    </div>
@endsection
