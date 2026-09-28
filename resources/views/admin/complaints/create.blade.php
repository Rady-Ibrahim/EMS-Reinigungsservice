@extends('admin.layouts.app')
@section('title', __('complaints.flash.created'))

@section('content')
<div class="card" style="max-width:760px">
    <div class="card-header"><span class="card-title">{{ __('complaints.flash.created') }}</span></div>

    <form method="POST" action="{{ route('admin.complaints.store') }}" style="padding:1rem">
        @csrf

        <div class="form-group">
            <label>{{ __('messages.cal.title') }} *</label>
            <input type="text" name="title" class="form-control" value="{{ old('title') }}" maxlength="191" required>
            @error('title') <span style="color:#ef4444;font-size:.8rem">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label>{{ __('messages.cal.location') }} *</label>
            <select name="location_id" class="form-control" required>
                <option value="">— {{ __('cal_page.select_placeholder') }} —</option>
                @foreach(\App\Models\CustomerLocation::orderBy('name')->get() as $loc)
                    <option value="{{ $loc->id }}" @selected(old('location_id') == $loc->id)>{{ $loc->name }}</option>
                @endforeach
            </select>
            @error('location_id') <span style="color:#ef4444;font-size:.8rem">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label>{{ __('messages.cal.customer') }}</label>
            <input type="number" name="client_id" class="form-control" value="{{ old('client_id') }}">
            @error('client_id') <span style="color:#ef4444;font-size:.8rem">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label>{{ __('messages.cal.staff') }}</label>
            <select name="assigned_employee_id" class="form-control">
                <option value="">— {{ __('cal_page.select_placeholder') }} —</option>
                @foreach(\App\Models\User::orderBy('name')->get() as $u)
                    <option value="{{ $u->id }}" @selected(old('assigned_employee_id') == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label>{{ __('cal_page.reason') }}</label>
            <input type="datetime-local" name="occurred_at" class="form-control" value="{{ old('occurred_at') }}">
        </div>

        <div class="form-group">
            <label>{{ __('messages.cal.description') }} *</label>
            <textarea name="description" rows="4" class="form-control" maxlength="5000" required>{{ old('description') }}</textarea>
            @error('description') <span style="color:#ef4444;font-size:.8rem">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn btn-primary">{{ __('messages.save') }}</button>
        <a href="{{ route('admin.complaints.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
    </form>
</div>
@endsection
