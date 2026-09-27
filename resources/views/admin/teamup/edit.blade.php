@extends('admin.layouts.app')

@section('title', __('teamup.title'))
@section('content')
    <h1>{{ __('teamup.heading') }}</h1>

    <div class="card" style="max-width:760px;">
        @if($isConfigured)
            <div class="alert alert-success">{{ __('teamup.configured') }}</div>
        @else
            <div class="alert alert-error">{{ __('teamup.not_configured') }}</div>
        @endif

        <form action="{{ route('admin.teamup.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label>
                    <input type="checkbox" name="enabled" value="1" {{ old('enabled', $settings->enabled) ? 'checked' : '' }}>
                    {{ __('teamup.enable_sync') }}
                </label>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>{!! __('teamup.calendar_key_label') !!}</label>
                    <input type="password" name="calendar_key" value="{{ old('calendar_key', $settings->calendar_key ?? '') }}" placeholder="ksXXXXXXXX">
                    <small>{{ __('teamup.calendar_key_help') }}</small>
                    @error('calendar_key') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label>{{ __('teamup.api_key_label') }}</label>
                    <input type="password" name="api_key" value="{{ old('api_key', $settings->api_key ?? '') }}">
                    @error('api_key') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-group">
                <label>{{ __('teamup.timezone_label') }}</label>
                <input type="text" name="timezone" value="{{ old('timezone', $settings->timezone ?? 'Europe/Berlin') }}">
            </div>

            <h3 style="font-size:1rem;margin:.75rem 0;">{{ __('teamup.subcalendar_heading') }}</h3>
            @php
                $typeLabels = [
                    'default'                   => __('teamup.type_default'),
                    'fix_schedule'              => __('teamup.type_fix_schedule'),
                    'extra_auftrag'             => __('teamup.type_extra_auftrag'),
                    'employee_shift'            => __('teamup.type_employee_shift'),
                    'personal_appointment'      => __('teamup.type_personal_appointment'),
                    'internal_event'            => __('teamup.type_internal_event'),
                ];
            @endphp

            <div class="form-row" style="grid-template-columns:1fr 1fr;">
                @foreach($typeLabels as $key => $label)
                    <div class="form-group">
                        <label>{{ $label }}</label>
                        <input type="number" name="subcalendar_{{ $key }}"
                               value="{{ old('subcalendar_' . $key, $settings->subcalendarFor($key) ?? '') }}" placeholder="{{ __('teamup.subcalendar_id_placeholder') }}">
                    </div>
                @endforeach
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">{{ __('teamup.save') }}</button>
            </div>
        </form>

        <hr style="margin:1.5rem 0;border:none;border-top:1px solid #e2e8f0;">

        <form action="{{ route('admin.teamup.sync') }}" method="POST">
            @csrf
            <div style="display:flex;align-items:center;gap:1rem;justify-content:space-between;">
                <div style="font-size:.85rem;color:#475569;">
                    {{ __('teamup.last_sync') }} {{ $lastSync?->last_pushed_at?->format('d.m.Y H:i') ?? '—' }}
                </div>
                <button type="submit" class="btn btn-secondary">{{ __('teamup.sync_now') }}</button>
            </div>
        </form>
    </div>
@endsection