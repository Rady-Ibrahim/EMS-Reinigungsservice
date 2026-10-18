@php
    $initialLocations = old('locations', $existingLocations ?? []);
@endphp

<hr style="border:none;border-top:1px solid var(--border);margin:1.25rem 0">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
    <h2 style="font-size:1rem;color:var(--text-muted)">{{ __('customer.locations') }}</h2>
    <button type="button" class="btn btn-secondary btn-sm" onclick="addLocation()">+ {{ __('customer.add_location') }}</button>
</div>

<div id="locationsContainer"></div>
<p style="color:var(--text-faint);font-size:.8rem" id="noLocationsHint">{{ __('customer.no_locations') }}</p>

<template id="locationTemplate">
    <div class="location-row" style="border:1px solid var(--border);border-radius:10px;padding:1rem;margin-bottom:.75rem;background:var(--bg-soft)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
            <strong style="font-size:.85rem;color:var(--gold-light)">{{ __('customer.location') }} <span class="loc-index"></span></strong>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.location-row').remove(); refreshLocationIndexes();">✕</button>
        </div>
        <input type="hidden" data-field="id">
        <div class="form-row">
            <div class="form-group"><label>{{ __('locations.name') }}</label><input type="text" data-field="name"></div>
            <div class="form-group"><label>{{ __('locations.contact_person') }}</label><input type="text" data-field="contact_person"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>{{ __('locations.street') }}</label><input type="text" data-field="street"></div>
            <div class="form-group"><label>{{ __('locations.house_number') }}</label><input type="text" data-field="house_number"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>{{ __('locations.postal_code') }}</label><input type="text" data-field="postal_code"></div>
            <div class="form-group"><label>{{ __('locations.city') }}</label><input type="text" data-field="city"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>{{ __('locations.contact_phone') }}</label><input type="tel" data-field="contact_phone"></div>
            <div class="form-group"><label>{{ __('locations.security_code') }}</label><input type="text" data-field="security_code"></div>
        </div>
        <div class="form-group"><label>{{ __('locations.access_instructions') }}</label><textarea data-field="access_instructions" rows="2"></textarea></div>
    </div>
</template>

<script>
const initialLocations = @json($initialLocations ?? []);

function addLocation(data) {
    const tpl = document.getElementById('locationTemplate').content.cloneNode(true);
    const row = tpl.querySelector('.location-row');
    document.getElementById('locationsContainer').appendChild(row);
    const index = document.querySelectorAll('.location-row').length - 1;
    row.dataset.index = index;

    if (data) {
        Object.keys(data).forEach(k => {
            const el = row.querySelector(`[data-field="${k}"]`);
            if (el && data[k] !== null) el.value = data[k];
        });
    }
    refreshLocationIndexes();
}

function refreshLocationIndexes() {
    const rows = document.querySelectorAll('.location-row');
    rows.forEach((row, i) => {
        row.dataset.index = i;
        row.querySelectorAll('[data-field]').forEach(el => {
            el.name = `locations[${i}][${el.dataset.field}]`;
        });
        row.querySelector('.loc-index').textContent = '#' + (i + 1);
    });
    document.getElementById('noLocationsHint').style.display = rows.length ? 'none' : 'block';
}

initialLocations.forEach(addLocation);
refreshLocationIndexes();
</script>
