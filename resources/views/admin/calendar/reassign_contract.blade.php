@extends('admin.layouts.app')

@section('title', __('cal_page.reassign_fix_object_title'))
@section('content')
    <h1>{{ __('cal_page.reassign_fix_object_heading') }}</h1>

    <div class="card" style="max-width:720px;">
        <div style="margin-bottom:1rem;font-size:.875rem;">
            <strong>{{ $fixObject->title }}</strong>
            @if($fixObject->customer)<div>{{ __('cal_page.customer') }}: {{ $fixObject->customer->name }}</div>@endif
            <div style="margin-top:.4rem;">
                @foreach($fixObject->assignments as $assignment)
                    <span class="badge badge-blue">
                        {{ $assignment->user?->name }}
                        (<code>{{ $assignment->assigned_from }}</code> – <code>{{ $assignment->assigned_until ?? __('cal_page.open') }}</code>)
                    </span>
                @endforeach
            </div>
        </div>

        <form action="{{ route('admin.fix-objects.reassign.store', $fixObject) }}" method="POST">
            @csrf

            <div class="form-group">
                <label>{{ __('cal_page.new_employee') }} *</label>
                <select name="user_id" required>
                    <option value="">{{ __('cal_page.select_placeholder') }}</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" {{ old('user_id') == $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>
                    @endforeach
                </select>
                @error('user_id') <div class="field-error">{{ $message }}</div> @enderror
                @error('conflicts') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>{{ __('cal_page.from_date') }} *</label>
                    <input type="date" name="assigned_from" value="{{ old('assigned_from', now()->toDateString()) }}" required>
                    @error('assigned_from') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label>{{ __('cal_page.until_optional') }}</label>
                    <input type="date" name="assigned_until" value="{{ old('assigned_until') }}">
                    @error('assigned_until') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-group">
                <label>{{ __('cal_page.reason') }}</label>
                <textarea name="reason" rows="2">{{ old('reason') }}</textarea>
            </div>

            @include('admin.calendar.partials.force_checkbox')

            <div class="actions">
                <button type="submit" class="btn btn-primary">{{ __('cal_page.reassign') }}</button>
                <a href="{{ route('admin.fix-objects.show', $fixObject) }}" class="btn btn-secondary">{{ __('cal_page.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
