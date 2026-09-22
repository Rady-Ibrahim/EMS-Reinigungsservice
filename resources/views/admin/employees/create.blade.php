@extends('admin.layouts.app')
@section('title', __('messages.employees.create'))

@section('content')
<h1>{{ __('messages.employees.create') }}</h1>

<div class="card">
    <form action="{{ route('admin.employees.store') }}" method="POST">
        @csrf

        <h2 style="font-size:1rem;margin-bottom:.75rem;color:#475569">Account-Daten</h2>
        <div class="form-row">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" value="{{ old('name') }}" required>
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label>E-Mail *</label>
                <input type="email" name="email" value="{{ old('email') }}" required>
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Passwort *</label>
                <input type="password" name="password" required>
                @error('password')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label>Rolle *</label>
                <select name="role" required>
                    <option value="vorarbeiter" {{ old('role') === 'vorarbeiter' ? 'selected' : '' }}>Vorarbeiter</option>
                    <option value="mitarbeiter" {{ old('role','mitarbeiter') === 'mitarbeiter' ? 'selected' : '' }}>Mitarbeiter</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Sprache</label>
                <select name="locale">
                    <option value="de" {{ old('locale','de') === 'de' ? 'selected' : '' }}>Deutsch</option>
                    <option value="ar" {{ old('locale') === 'ar' ? 'selected' : '' }}>العربية</option>
                    <option value="en" {{ old('locale') === 'en' ? 'selected' : '' }}>English</option>
                </select>
            </div>
            <div class="form-group">
                <label>Kalenderfarbe</label>
                <input type="text" name="calendar_color" value="{{ old('calendar_color', '#3b82f6') }}" placeholder="#3b82f6">
                @error('calendar_color')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:1rem 0">
        <h2 style="font-size:1rem;margin-bottom:.75rem;color:#475569">Profil-Daten</h2>
        <div class="form-row">
            <div class="form-group">
                <label>Mitarbeiternummer</label>
                <input type="text" name="employee_number" value="{{ old('employee_number') }}">
            </div>
            <div class="form-group">
                <label>Telefon</label>
                <input type="tel" name="phone" value="{{ old('phone') }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Vertragsart</label>
                <select name="contract_type">
                    <option value="minijob" {{ old('contract_type','minijob') === 'minijob' ? 'selected' : '' }}>Minijob</option>
                    <option value="teilzeit" {{ old('contract_type') === 'teilzeit' ? 'selected' : '' }}>Teilzeit</option>
                    <option value="vollzeit" {{ old('contract_type') === 'vollzeit' ? 'selected' : '' }}>Vollzeit</option>
                </select>
            </div>
            <div class="form-group">
                <label>Eintrittsdatum</label>
                <input type="date" name="joined_at" value="{{ old('joined_at') }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Stundenlohn (intern, vertraulich)</label>
                <input type="number" name="hourly_rate" step="0.01" value="{{ old('hourly_rate') }}" placeholder="0.00">
                @error('hourly_rate')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label>IBAN (verschlüsselt)</label>
                <input type="text" name="iban" value="{{ old('iban') }}" placeholder="DE89 3704 0044 0532 0130 00">
            </div>
        </div>
        <div class="form-group">
            <label>Interne Notizen</label>
            <textarea name="notes" rows="3">{{ old('notes') }}</textarea>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:.5rem">
            <button type="submit" class="btn btn-primary">Speichern</button>
            <a href="{{ route('admin.employees.index') }}" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
@endsection
