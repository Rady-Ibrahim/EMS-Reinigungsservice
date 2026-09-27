@extends('admin.layouts.app')
@section('title', __('messages.customers.edit'))

@section('content')
<h1>{{ __('messages.customers.edit') }}: {{ $customer->name }}</h1>

<div class="card">
    <form action="{{ route('admin.customers.update', $customer) }}" method="POST">
        @csrf @method('PUT')

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('customer.name') }}</label>
                <input type="text" name="name" value="{{ old('name', $customer->name) }}" required>
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label>{{ __('customer.contact_person') }}</label>
                <input type="text" name="contact_person" value="{{ old('contact_person', $customer->contact_person) }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('customer.email') }}</label>
                <input type="email" name="email" value="{{ old('email', $customer->email) }}">
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label>{{ __('customer.phone') }}</label>
                <input type="tel" name="phone" value="{{ old('phone', $customer->phone) }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('customer.status') }}</label>
                <select name="status">
                    <option value="active" {{ old('status', $customer->status->value) === 'active' ? 'selected' : '' }}>{{ __('customer.active') }}</option>
                    <option value="inactive" {{ old('status', $customer->status->value) === 'inactive' ? 'selected' : '' }}>{{ __('customer.inactive') }}</option>
                </select>
            </div>
            <div class="form-group">
                <label>{{ __('customer.portal') }}</label>
                <select name="portal_access">
                    <option value="0" {{ ! old('portal_access', $customer->portal_access) ? 'selected' : '' }}>{{ __('customer.no') }}</option>
                    <option value="1" {{ old('portal_access', $customer->portal_access) ? 'selected' : '' }}>{{ __('customer.yes') }}</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>{{ __('customer.portal_email') }}</label>
            <input type="email" name="portal_email" value="{{ old('portal_email', $customer->portal_email) }}">
        </div>

        <div class="form-group">
            <label>{{ __('customer.notes') }}</label>
            <textarea name="notes" rows="3">{{ old('notes', $customer->notes) }}</textarea>
        </div>

        <div style="display:flex;gap:.75rem">
            <button type="submit" class="btn btn-primary">{{ __('customer.save') }}</button>
            <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-secondary">{{ __('customer.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
