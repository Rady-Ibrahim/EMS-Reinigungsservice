@extends('admin.layouts.app')

@section('title', $event->exists ? __('cal_page.edit_internal_event') : __('cal_page.new_internal_event'))
@section('content')
    <h1>{{ $event->exists ? __('cal_page.edit_internal_event') : __('cal_page.new_internal_event') }}</h1>

    <div class="card" style="max-width:720px;">
        <form action="{{ $event->exists ? route('admin.calendar.internal-events.update', $event) : route('admin.calendar.internal-events.store') }}" method="POST">
            @csrf
            @if($event->exists) @method('PUT') @endif

            <div class="form-group">
                <label>{{ __('cal_page.title') }} *</label>
                <input type="text" name="title" value="{{ old('title', $event->title) }}" required>
                @error('title') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>{{ __('cal_page.type') }} *</label>
                    <select name="event_type" required>
                        @foreach($eventTypes as $type)
                            <option value="{{ $type }}" {{ old('event_type', $event->event_type?->value) === $type ? 'selected' : '' }}>
                                {{ \App\Enums\InternalEventTypeEnum::from($type)->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>{{ __('cal_page.location') }}</label>
                    <input type="text" name="location" value="{{ old('location', $event->location) }}">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>{{ __('cal_page.start') }} *</label>
                    <input type="datetime-local" name="start_at"
                           value="{{ old('start_at', $event->start_at?->format('Y-m-d\TH:i')) }}" required>
                    @error('start_at') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label>{{ __('cal_page.end') }} *</label>
                    <input type="datetime-local" name="end_at"
                           value="{{ old('end_at', $event->end_at?->format('Y-m-d\TH:i')) }}" required>
                    @error('end_at') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-group">
                <label>{{ __('cal_page.participants') }}</label>
                <select name="attendee_ids[]" multiple size="6">
                    @foreach($users as $user)
                        <option value="{{ $user->id }}"
                            {{ in_array($user->id, old('attendee_ids', $event->assignees->pluck('user_id')->all()), true) ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
                <small>{{ __('cal_page.multi_select_hint') }}</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>{{ __('cal_page.all_day') }}</label>
                    <input type="checkbox" name="all_day" value="1" {{ old('all_day', $event->all_day) ? 'checked' : '' }}>
                </div>
                <div class="form-group">
                    <label>{{ __('cal_page.color') }}</label>
                    <input type="color" name="color" value="{{ old('color', $event->color ?? '#f59e0b') }}">
                </div>
            </div>

            <div class="form-group">
                <label>{{ __('cal_page.description') }}</label>
                <textarea name="description" rows="3">{{ old('description', $event->description) }}</textarea>
            </div>

            @include('admin.calendar.partials.force_checkbox')

            <div class="actions">
                <button type="submit" class="btn btn-primary">{{ __('cal_page.save') }}</button>
                <a href="{{ route('admin.calendar.internal-events.index') }}" class="btn btn-secondary">{{ __('cal_page.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
