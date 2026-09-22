@extends('admin.layouts.app')
@section('title', __('messages.employees.edit'))

@section('content')
<h1>{{ __('messages.employees.edit') }}: {{ $employee->name }}</h1>

<div class="card">
    <form action="{{ route('admin.employees.update', $employee) }}" method="POST">
        @csrf @method('PUT')

        <div class="form-row">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" value="{{ old('name', $employee->name) }}" required>
            </div>
            <div class="form-group">
                <label>E-Mail *</label>
                <input type="email" name="email" value="{{ old('email', $employee->email) }}" required>
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Neues Passwort (leer lassen = unverändert)</label>
                <input type="password" name="password">
            </div>
            <div class="form-group">
                <label>Rolle *</label>
                <select name="role" required>
                    <option value="vorarbeiter" {{ old('role', $employee->role->value) === 'vorarbeiter' ? 'selected' : '' }}>Vorarbeiter</option>
                    <option value="mitarbeiter" {{ old('role', $employee->role->value) === 'mitarbeiter' ? 'selected' : '' }}>Mitarbeiter</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Sprache</label>
                <select name="locale">
                    <option value="de" {{ old('locale', $employee->locale) === 'de' ? 'selected' : '' }}>Deutsch</option>
                    <option value="ar" {{ old('locale', $employee->locale) === 'ar' ? 'selected' : '' }}>العربية</option>
                    <option value="en" {{ old('locale', $employee->locale) === 'en' ? 'selected' : '' }}>English</option>
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="is_active">
                    <option value="1" {{ old('is_active', $employee->is_active) ? 'selected' : '' }}>Aktiv</option>
                    <option value="0" {{ ! old('is_active', $employee->is_active) ? 'selected' : '' }}>Inaktiv</option>
                </select>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:1rem 0">

        <div class="form-row">
            <div class="form-group">
                <label>Kalenderfarbe</label>
                <input type="text" name="calendar_color" value="{{ old('calendar_color', $employee->employeeProfile?->calendar_color) }}">
            </div>
            <div class="form-group">
                <label>Mitarbeiternummer</label>
                <input type="text" name="employee_number" value="{{ old('employee_number', $employee->employeeProfile?->employee_number) }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Stundenlohn (intern)</label>
                <input type="number" name="hourly_rate" step="0.01"
                    value="{{ old('hourly_rate', $employee->employeeProfile?->getHourlyRateAsFloat()) }}">
            </div>
            <div class="form-group">
                <label>IBAN</label>
                <input type="text" name="iban" value="{{ old('iban', $employee->employeeProfile?->iban) }}">
            </div>
        </div>
        <div class="form-group">
            <label>Notizen</label>
            <textarea name="notes" rows="3">{{ old('notes', $employee->employeeProfile?->notes) }}</textarea>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:.5rem">
            <button type="submit" class="btn btn-primary">Speichern</button>
            <a href="{{ route('admin.employees.show', $employee) }}" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
@endsection
