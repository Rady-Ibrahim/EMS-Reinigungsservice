@extends('admin.layouts.app')
@section('title', __('employee.create_title'))

@section('content')
<h1>{{ __('employee.create_title') }}</h1>

<div class="card">
    <form action="{{ route('admin.employees.store') }}" method="POST">
        @csrf

        <h2 style="font-size:1rem;margin-bottom:.75rem;color:#475569">{{ __('employee.account_data') }}</h2>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('employee.name_required') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required>
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label>{{ __('employee.email_required') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" required>
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('employee.password') }}</label>
                <input type="password" name="password" required>
                @error('password')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label>{{ __('employee.role_required') }}</label>
                <select name="role" required>
                    <option value="vorarbeiter" {{ old('role') === 'vorarbeiter' ? 'selected' : '' }}>{{ __('employee.role_vorarbeiter') }}</option>
                    <option value="mitarbeiter" {{ old('role','mitarbeiter') === 'mitarbeiter' ? 'selected' : '' }}>{{ __('employee.role_mitarbeiter') }}</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('employee.locale') }}</label>
                <select name="locale">
                    <option value="de" {{ old('locale','de') === 'de' ? 'selected' : '' }}>Deutsch</option>
                    <option value="ar" {{ old('locale') === 'ar' ? 'selected' : '' }}>العربية</option>
                    <option value="en" {{ old('locale') === 'en' ? 'selected' : '' }}>English</option>
                </select>
            </div>
            <div class="form-group">
                <label>{{ __('company.company') }}</label>
                <select name="company_id">
                    <option value="">{{ __('employee.no_company') }}</option>
                    @foreach($companies as $company)
                    <option value="{{ $company->id }}" {{ (string) old('company_id') === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                    @endforeach
                </select>
                @error('company_id')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:1rem 0">
        <h2 style="font-size:1rem;margin-bottom:.75rem;color:#475569">{{ __('employee.profile_data') }}</h2>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('employee.employee_number') }}</label>
                <input type="text" name="employee_number" value="{{ old('employee_number') }}">
            </div>
            <div class="form-group">
                <label>{{ __('employee.phone') }}</label>
                <input type="tel" name="phone" value="{{ old('phone') }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('employee.contract_type') }}</label>
                <select name="contract_type">
                    <option value="minijob" {{ old('contract_type','minijob') === 'minijob' ? 'selected' : '' }}>{{ __('employee.contract_minijob') }}</option>
                    <option value="teilzeit" {{ old('contract_type') === 'teilzeit' ? 'selected' : '' }}>{{ __('employee.contract_teilzeit') }}</option>
                    <option value="vollzeit" {{ old('contract_type') === 'vollzeit' ? 'selected' : '' }}>{{ __('employee.contract_vollzeit') }}</option>
                </select>
            </div>
            <div class="form-group">
                <label>{{ __('employee.joined_at') }}</label>
                <input type="date" name="joined_at" value="{{ old('joined_at') }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('employee.hourly_rate_confidential') }}</label>
                <input type="number" name="hourly_rate" step="0.01" value="{{ old('hourly_rate') }}" placeholder="0.00">
                @error('hourly_rate')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label>{{ __('employee.iban_encrypted') }}</label>
                <input type="text" name="iban" value="{{ old('iban') }}" placeholder="DE89 3704 0044 0532 0130 00">
            </div>
        </div>
        <div class="form-group">
            <label>{{ __('employee.notes_internal') }}</label>
            <textarea name="notes" rows="3">{{ old('notes') }}</textarea>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:.5rem">
            <button type="submit" class="btn btn-primary">{{ __('employee.save') }}</button>
            <a href="{{ route('admin.employees.index') }}" class="btn btn-secondary">{{ __('employee.cancel') }}</a>
        </div>
    </form>
</div>
@endsection