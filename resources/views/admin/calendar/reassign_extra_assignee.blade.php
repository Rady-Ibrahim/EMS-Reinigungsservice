@extends('admin.layouts.app')

@section('title', __('cal_page.swap_team_member_title'))
@section('content')
    <h1>{{ __('cal_page.swap_extra_assignee_heading') }}</h1>

    <div class="card" style="max-width:720px;">
        <div style="margin-bottom:1rem;font-size:.875rem;">
            <strong>{{ $assignee->extraAuftrag?->title }}</strong>
            <span class="badge badge-blue">{{ $assignee->extraAuftrag?->scheduled_date?->format('d.m.Y') }}</span>
            <div>{{ __('cal_page.currently') }} <strong>{{ $assignee->user?->name }}</strong> ({{ $assignee->role_in_order->label() }})</div>
            @error('conflicts') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <form action="{{ route('admin.extra-auftraege.assignees.reassign.store', $assignee) }}" method="POST">
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
            </div>

            @include('admin.calendar.partials.force_checkbox')

            <div class="actions">
                <button type="submit" class="btn btn-primary">{{ __('cal_page.swap') }}</button>
                <a href="{{ route('admin.extra-auftraege.show', $assignee->extra_auftrag_id) }}" class="btn btn-secondary">{{ __('cal_page.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
