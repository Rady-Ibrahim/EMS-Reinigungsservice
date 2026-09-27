@extends('admin.layouts.app')

@section('title', __('cal_page.reassign_schedule_title'))
@section('content')
    <h1>{{ __('cal_page.reassign_schedule_heading') }}</h1>

    <div class="card" style="max-width:720px;">
        <div style="margin-bottom:1rem;font-size:.875rem;">
            <strong>{{ $schedule->fixObject->title }}</strong>
            <span class="badge badge-yellow">{{ $schedule->scheduled_date->format('d.m.Y') }}</span>
            <span class="badge badge-gray">{{ $schedule->scheduled_start ? substr($schedule->scheduled_start,0,5).' – '.substr($schedule->scheduled_end,0,5) : __('cal_page.all_day') }}</span>
            <div style="margin-top:.4rem;">
                @foreach($fixObject->assignments as $assignment)
                    <span class="badge badge-blue">{{ $assignment->user?->name }} ({{ __('cal_page.contract') }})</span>
                @endforeach
                @foreach($schedule->scheduleAssignments as $override)
                    <span class="badge badge-green">{{ $override->user?->name }} ({{ __('cal_page.override') }})</span>
                @endforeach
            </div>
        </div>

        <form action="{{ route('admin.calendar.schedules.reassign.store', $schedule) }}" method="POST">
            @csrf

            <div class="form-group">
                <label>{{ __('cal_page.employee_for_day') }} *</label>
                <select name="user_id" required>
                    <option value="">{{ __('cal_page.select_placeholder') }}</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" {{ old('user_id') == $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>
                    @endforeach
                </select>
                @error('user_id') <div class="field-error">{{ $message }}</div> @enderror
                @error('conflicts') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label>{{ __('cal_page.reason') }}</label>
                <textarea name="reason" rows="2">{{ old('reason') }}</textarea>
            </div>

            @include('admin.calendar.partials.force_checkbox')

            <div class="actions">
                <button type="submit" class="btn btn-primary">{{ __('cal_page.reassign') }}</button>
                <a href="{{ route('admin.calendar.index') }}" class="btn btn-secondary">{{ __('cal_page.cancel') }}</a>
            </div>
        </form>

        @if($schedule->isCompleted())
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:1.5rem 0;">
        <div>
            <h2 class="card-title" style="margin-bottom:.5rem">{{ __('cal_page.reopen_appointment') }}</h2>
            <p style="font-size:.85rem;color:#475569;margin-bottom:.75rem;">
                {{ __('cal_page.reopen_info_prefix') }}<strong>{{ __('cal_page.in_progress') }}</strong>{{ __('cal_page.reopen_info_suffix') }}
            </p>
            <form method="POST" action="{{ route('admin.calendar.schedules.reopen', $schedule) }}">
                @csrf
                <div class="form-group">
                    <label for="reopen_reason">{{ __('cal_page.justification') }} *</label>
                    <textarea id="reopen_reason" name="reopen_reason" rows="2" required>{{ old('reopen_reason') }}</textarea>
                    @error('reopen_reason')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-danger">{{ __('cal_page.reopen_appointment') }}</button>
            </form>
        </div>
        @endif
    </div>
@endsection
