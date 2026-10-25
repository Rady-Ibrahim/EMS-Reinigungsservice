@extends('admin.layouts.app')

@section('title', __('cal_page.edit_schedule'))
@section('content')
    <h1>{{ __('cal_page.edit_schedule') }}</h1>

    <div class="card" style="max-width:720px;">
        <p style="margin-bottom:1rem"><strong>{{ $schedule->fixObject->title }}</strong></p>
        <form action="{{ route('admin.calendar.schedules.update', $schedule) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label>{{ __('cal_page.date') }} *</label>
                <input type="date" name="scheduled_date"
                       value="{{ old('scheduled_date', $schedule->scheduled_date->format('Y-m-d')) }}" required>
                @error('scheduled_date') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>{{ __('cal_page.start') }}</label>
                    <input type="time" name="scheduled_start"
                           value="{{ old('scheduled_start', $schedule->scheduled_start ? substr($schedule->scheduled_start, 0, 5) : '') }}">
                    @error('scheduled_start') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label>{{ __('cal_page.end') }}</label>
                    <input type="time" name="scheduled_end"
                           value="{{ old('scheduled_end', $schedule->scheduled_end ? substr($schedule->scheduled_end, 0, 5) : '') }}">
                    @error('scheduled_end') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>
            <p style="font-size:.85rem;color:#64748b">{{ __('cal_page.schedule_edit_hint') }}</p>
            @error('conflicts') <div class="field-error">{{ $message }}</div> @enderror

            @include('admin.calendar.partials.force_checkbox')

            <div class="actions">
                <button type="submit" class="btn btn-primary">{{ __('cal_page.save') }}</button>
                <a href="{{ route('admin.calendar.index') }}" class="btn btn-secondary">{{ __('cal_page.cancel') }}</a>
                <a href="{{ route('admin.calendar.schedules.reassign', $schedule) }}" class="btn btn-secondary">{{ __('cal_page.reassign') }}</a>
                <a href="{{ route('admin.fix-objects.edit', $schedule->fixObject) }}" class="btn btn-secondary">{{ __('cal_page.edit_contract') }}</a>
            </div>
        </form>
    </div>
@endsection
