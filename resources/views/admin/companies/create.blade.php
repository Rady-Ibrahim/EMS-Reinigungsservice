@extends('admin.layouts.app')
@section('title', __('company.new'))

@section('content')
<h1>{{ __('company.new') }}</h1>

<div class="card">
    <form action="{{ route('admin.companies.store') }}" method="POST">
        @csrf

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('company.name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required>
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label>{{ __('company.contact_person') }}</label>
                <input type="text" name="contact_person" value="{{ old('contact_person') }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('company.phone') }}</label>
                <input type="tel" name="phone" value="{{ old('phone') }}">
            </div>
        </div>

        <div class="form-group">
            <label>{{ __('company.notes') }}</label>
            <textarea name="notes" rows="3">{{ old('notes') }}</textarea>
        </div>

        <div style="display:flex;gap:.75rem">
            <button type="submit" class="btn btn-primary">{{ __('company.save') }}</button>
            <a href="{{ route('admin.companies.index') }}" class="btn btn-secondary">{{ __('company.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
