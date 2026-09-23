@extends('admin.layouts.app')
@section('title', 'Neues Fixobjekt')

@section('content')
<h1>Neues Fixobjekt</h1>

<div class="card">
    <form action="{{ route('admin.fix-objects.store') }}" method="POST" id="fixObjectForm">
        @csrf

        <h2 style="font-size:1rem;margin-bottom:.75rem;color:#475569">Standort & Vertrag</h2>
        <div class="form-row">
            <div class="form-group">
                <label>Kunde *</label>
                <select name="customer_id" id="customerSelect" required>
                    <option value="">— Kunde wählen —</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
                @error('customer_id')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label>Standort *</label>
                <select name="location_id" id="locationSelect" required>
                    <option value="">— Zuerst Kunde wählen —</option>
                </select>
                @error('location_id')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Titel *</label>
                <input type="text" name="title" value="{{ old('title') }}" required placeholder="z.B. Büroreinigung Mo/Mi/Fr">
            </div>
            <div class="form-group">
                <label>Kalenderfarbe (Admin)</label>
                <input type="text" name="calendar_color" value="{{ old('calendar_color', '#FFD700') }}" placeholder="#FFD700">
                <small style="color:#94a3b8">Standard: Gold #FFD700</small>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:1rem 0">
        <h2 style="font-size:1rem;margin-bottom:.75rem;color:#475569">Frequenz & Zeiten</h2>
        <div class="form-row">
            <div class="form-group">
                <label>Frequenz *</label>
                <select name="frequency" required>
                    <option value="daily"      {{ old('frequency') === 'daily'      ? 'selected' : '' }}>Täglich</option>
                    <option value="weekly"     {{ old('frequency') === 'weekly'     ? 'selected' : '' }}>1x pro Woche</option>
                    <option value="biweekly"   {{ old('frequency') === 'biweekly'   ? 'selected' : '' }}>2x pro Woche</option>
                    <option value="triweekly"  {{ old('frequency','triweekly') === 'triweekly'  ? 'selected' : '' }}>3x pro Woche</option>
                    <option value="monthly"    {{ old('frequency') === 'monthly'    ? 'selected' : '' }}>1x pro Monat</option>
                </select>
            </div>
            <div class="form-group">
                <label>Vertragsstunden (pro Einsatz) *</label>
                <input type="number" name="contract_hours" step="0.25" min="0.25" max="24"
                       value="{{ old('contract_hours', '2.00') }}" required>
            </div>
        </div>

        <div class="form-group">
            <label>Wochentage</label>
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
                <label>Uhrzeit Start</label>
                <input type="time" name="time_start" value="{{ old('time_start') }}">
            </div>
            <div class="form-group">
                <label>Uhrzeit Ende</label>
                <input type="time" name="time_end" value="{{ old('time_end') }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Gültig ab *</label>
                <input type="date" name="valid_from" value="{{ old('valid_from', now()->toDateString()) }}" required>
            </div>
            <div class="form-group">
                <label>Gültig bis (leer = unbefristet)</label>
                <input type="date" name="valid_until" value="{{ old('valid_until') }}">
            </div>
        </div>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:1rem 0">
        <h2 style="font-size:1rem;margin-bottom:.5rem;color:#475569">💰 Finanzdaten <small style="font-weight:400;font-size:.8rem">(Admin only – verschlüsselt)</small></h2>
        <div class="form-row">
            <div class="form-group">
                <label>Preis / Monat (€)</label>
                <input type="number" name="price_per_month" step="0.01" min="0" value="{{ old('price_per_month') }}">
            </div>
            <div class="form-group">
                <label>Preis / Stunde (€)</label>
                <input type="number" name="price_per_hour" step="0.01" min="0" value="{{ old('price_per_hour') }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Interne Kosten (€)</label>
                <input type="number" name="internal_cost" step="0.01" min="0" value="{{ old('internal_cost') }}">
            </div>
            <div class="form-group">
                <label>Gewinnmarge (€ / %)</label>
                <input type="number" name="profit_margin" step="0.01" value="{{ old('profit_margin') }}">
            </div>
        </div>
        <div class="form-group">
            <label>Interne Notizen</label>
            <textarea name="internal_notes" rows="3">{{ old('internal_notes') }}</textarea>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:.5rem">
            <button type="submit" class="btn btn-primary">Speichern & Termine generieren</button>
            <a href="{{ route('admin.fix-objects.index') }}" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>

<script>
document.getElementById('customerSelect').addEventListener('change', function() {
    const customerId = this.value;
    const locationSelect = document.getElementById('locationSelect');
    locationSelect.innerHTML = '<option value="">Lädt...</option>';

    if (!customerId) return;

    fetch(`/admin/api/customers/${customerId}/locations`)
        .then(r => r.json())
        .then(data => {
            locationSelect.innerHTML = '<option value="">— Standort wählen —</option>';
            data.forEach(loc => {
                locationSelect.innerHTML += `<option value="${loc.id}">${loc.name} – ${loc.city}</option>`;
            });
        });
});
</script>
@endsection
