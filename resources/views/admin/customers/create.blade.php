@extends('admin.layouts.app')
@section('title', __('messages.customers.create'))

@section('content')
<h1>{{ __('messages.customers.create') }}</h1>

<div class="card">
    <form action="{{ route('admin.customers.store') }}" method="POST">
        @csrf

        <div class="form-row">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" value="{{ old('name') }}" required>
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label>Kontaktperson</label>
                <input type="text" name="contact_person" value="{{ old('contact_person') }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>E-Mail</label>
                <input type="email" name="email" value="{{ old('email') }}">
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label>Telefon</label>
                <input type="tel" name="phone" value="{{ old('phone') }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="active" {{ old('status','active') === 'active' ? 'selected' : '' }}>Aktiv</option>
                    <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inaktiv</option>
                </select>
            </div>
            <div class="form-group">
                <label>Kundenportal-Zugang</label>
                <select name="portal_access">
                    <option value="0">Nein</option>
                    <option value="1" {{ old('portal_access') ? 'selected' : '' }}>Ja</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Portal-E-Mail</label>
            <input type="email" name="portal_email" value="{{ old('portal_email') }}">
            @error('portal_email')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label>Interne Notizen</label>
            <textarea name="notes" rows="3">{{ old('notes') }}</textarea>
        </div>

        <div style="display:flex;gap:.75rem">
            <button type="submit" class="btn btn-primary">Speichern</button>
            <a href="{{ route('admin.customers.index') }}" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
@endsection
