@extends('admin.layouts.app')
@section('title', __('fix_object.title_create'))

@section('content')
<h1>{{ __('fix_object.title_create') }}</h1>

<div class="card">
    <form action="{{ route('admin.fix-objects.store') }}" method="POST" id="fixObjectForm">
        @csrf

        <h2 style="font-size:1rem;margin-bottom:.75rem;color:#475569">{{ __('fix_object.location_contract_heading') }}</h2>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('fix_object.customer_label') }}</label>
                <select name="customer_id" id="customerSelect" required>
                    <option value="">{{ __('fix_object.customer_placeholder') }}</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
                @error('customer_id')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label>{{ __('fix_object.location_label') }}</label>
                <select name="location_id" id="locationSelect" required>
                    <option value="">{{ __('fix_object.location_placeholder_first') }}</option>
                </select>
                @error('location_id')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('fix_object.title_label') }}</label>
                <input type="text" name="title" value="{{ old('title') }}" required placeholder="{{ __('fix_object.title_placeholder') }}">
            </div>
            <div class="form-group">
                <label>{{ __('fix_object.calendar_color_admin') }}</label>
                <input type="text" name="calendar_color" value="{{ old('calendar_color', '#FFD700') }}" placeholder="#FFD700">
                <small style="color:#94a3b8">{{ __('fix_object.calendar_color_default') }}</small>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:1rem 0">
        <h2 style="font-size:1rem;margin-bottom:.75rem;color:#475569">{{ __('fix_object.frequency_times_heading') }}</h2>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('fix_object.frequency_label') }}</label>
                <select name="frequency" required>
                    <option value="daily"      {{ old('frequency') === 'daily'      ? 'selected' : '' }}>{{ __('fix_object.freq_daily') }}</option>
                    <option value="weekly"     {{ old('frequency') === 'weekly'     ? 'selected' : '' }}>{{ __('fix_object.freq_weekly') }}</option>
                    <option value="biweekly"   {{ old('frequency') === 'biweekly'   ? 'selected' : '' }}>{{ __('fix_object.freq_biweekly') }}</option>
                    <option value="triweekly"  {{ old('frequency','triweekly') === 'triweekly'  ? 'selected' : '' }}>{{ __('fix_object.freq_triweekly') }}</option>
                    <option value="monthly"    {{ old('frequency') === 'monthly'    ? 'selected' : '' }}>{{ __('fix_object.freq_monthly') }}</option>
                </select>
            </div>
            <div class="form-group">
                <label>{{ __('fix_object.contract_hours_label') }}</label>
                <input type="number" name="contract_hours" step="0.25" min="0.25" max="24"
                       value="{{ old('contract_hours', '2.00') }}" required>
            </div>
        </div>

        <div class="form-group">
            <label>{{ __('fix_object.weekdays') }}</label>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.25rem">
                @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)
                <label style="display:flex;align-items:center;gap:.3rem;font-weight:normal;cursor:pointer">
                    <input type="checkbox" name="frequency_days[]" value="{{ $day }}"
                           {{ in_array($day, old('frequency_days', [])) ? 'checked' : '' }}>
                    {{ $day }}
                </label>
                @endforeach
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('fix_object.time_start') }}</label>
                <input type="time" name="time_start" value="{{ old('time_start') }}">
            </div>
            <div class="form-group">
                <label>{{ __('fix_object.time_end') }}</label>
                <input type="time" name="time_end" value="{{ old('time_end') }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('fix_object.valid_from') }}</label>
                <input type="date" name="valid_from" value="{{ old('valid_from', now()->toDateString()) }}" required>
            </div>
            <div class="form-group">
                <label>{{ __('fix_object.valid_until_unlimited') }}</label>
                <input type="date" name="valid_until" value="{{ old('valid_until') }}">
            </div>
        </div>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:1rem 0">
        <h2 style="font-size:1rem;margin-bottom:.5rem;color:#475569">{{ __('fix_object.financial_data') }} <small style="font-weight:400;font-size:.8rem">{{ __('fix_object.finance_admin_note') }}</small></h2>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('fix_object.price_per_month') }}</label>
                <input type="number" name="price_per_month" step="0.01" min="0" value="{{ old('price_per_month') }}">
            </div>
            <div class="form-group">
                <label>{{ __('fix_object.price_per_hour') }}</label>
                <input type="number" name="price_per_hour" step="0.01" min="0" value="{{ old('price_per_hour') }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('fix_object.internal_cost') }}</label>
                <input type="number" name="internal_cost" step="0.01" min="0" value="{{ old('internal_cost') }}">
            </div>
            <div class="form-group">
                <label>{{ __('fix_object.profit_margin_pct') }}</label>
                <input type="number" name="profit_margin" step="0.01" value="{{ old('profit_margin') }}">
            </div>
        </div>
        <div class="form-group">
            <label>{{ __('fix_object.internal_notes') }}</label>
            <textarea name="internal_notes" rows="3">{{ old('internal_notes') }}</textarea>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:.5rem">
            <button type="submit" class="btn btn-primary">{{ __('fix_object.save_generate') }}</button>
            <a href="{{ route('admin.fix-objects.index') }}" class="btn btn-secondary">{{ __('fix_object.cancel') }}</a>
        </div>
    </form>
</div>

<script>
document.getElementById('customerSelect').addEventListener('change', function() {
    const customerId = this.value;
    const locationSelect = document.getElementById('locationSelect');
    locationSelect.innerHTML = '<option value="">' + @json(__('fix_object.loading')) + '</option>';

    if (!customerId) return;

    fetch(`/admin/api/customers/${customerId}/locations`)
        .then(r => r.json())
        .then(data => {
            locationSelect.innerHTML = '<option value="">' + @json(__('fix_object.location_placeholder')) + '</option>';
            data.forEach(loc => {
                locationSelect.innerHTML += `<option value="${loc.id}">${loc.name} – ${loc.city}</option>`;
            });
        });
});
</script>
@endsection