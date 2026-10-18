<h2 style="font-size:1rem;margin-bottom:.75rem;color:var(--text-muted)">{{ __('locations.basics') }}</h2>
<div class="form-row">
    <div class="form-group">
        <label>{{ __('locations.name') }} *</label>
        <input type="text" name="name" value="{{ old('name', $location->name ?? '') }}" required>
        @error('name')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="form-group">
        <label>{{ __('locations.status') }}</label>
        <select name="is_active">
            <option value="1" {{ old('is_active', $location->is_active ?? true) ? 'selected' : '' }}>{{ __('locations.active') }}</option>
            <option value="0" {{ old('is_active', $location->is_active ?? true) ? '' : 'selected' }}>{{ __('locations.inactive') }}</option>
        </select>
    </div>
</div>

<h2 style="font-size:1rem;margin:1rem 0 .75rem;color:var(--text-muted)">{{ __('locations.address') }}</h2>
<div class="form-row">
    <div class="form-group">
        <label>{{ __('locations.street') }} *</label>
        <input type="text" name="street" value="{{ old('street', $location->street ?? '') }}" required>
        @error('street')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="form-group">
        <label>{{ __('locations.house_number') }} *</label>
        <input type="text" name="house_number" value="{{ old('house_number', $location->house_number ?? '') }}" required>
        @error('house_number')<p class="field-error">{{ $message }}</p>@enderror
    </div>
</div>
<div class="form-row">
    <div class="form-group">
        <label>{{ __('locations.postal_code') }} *</label>
        <input type="text" name="postal_code" value="{{ old('postal_code', $location->postal_code ?? '') }}" required>
        @error('postal_code')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="form-group">
        <label>{{ __('locations.city') }} *</label>
        <input type="text" name="city" value="{{ old('city', $location->city ?? '') }}" required>
        @error('city')<p class="field-error">{{ $message }}</p>@enderror
    </div>
</div>

<h2 style="font-size:1rem;margin:1rem 0 .75rem;color:var(--text-muted)">{{ __('locations.access_heading') }}</h2>
<div class="form-row">
    <div class="form-group">
        <label>{{ __('locations.contact_person') }}</label>
        <input type="text" name="contact_person" value="{{ old('contact_person', $location->contact_person ?? '') }}">
    </div>
    <div class="form-group">
        <label>{{ __('locations.contact_phone') }}</label>
        <input type="tel" name="contact_phone" value="{{ old('contact_phone', $location->contact_phone ?? '') }}">
    </div>
</div>
<div class="form-group">
    <label>{{ __('locations.access_instructions') }}</label>
    <textarea name="access_instructions" rows="3">{{ old('access_instructions', $location->access_instructions ?? '') }}</textarea>
</div>
<div class="form-row">
    <div class="form-group">
        <label>{{ __('locations.security_code') }}</label>
        <input type="text" name="security_code" value="{{ old('security_code', $location->security_code ?? '') }}">
        @error('security_code')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="form-group">
        <label>{{ __('locations.working_hours') }}</label>
        <div style="display:flex;gap:.5rem">
            <input type="time" name="working_hours_start" value="{{ old('working_hours_start', $location->working_hours_start ?? '') }}">
            <input type="time" name="working_hours_end" value="{{ old('working_hours_end', $location->working_hours_end ?? '') }}">
        </div>
    </div>
</div>
