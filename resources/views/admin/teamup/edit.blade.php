@extends('admin.layouts.app')

@section('title', 'Teamup Einstellungen')
@section('content')
    <h1>Teamup Integration</h1>

    <div class="card" style="max-width:760px;">
        @if($isConfigured)
            <div class="alert alert-success">✓ Teamup ist konfiguriert und aktualisiert bei jedem Sync.</div>
        @else
            <div class="alert alert-error">Teamup ist noch nicht vollständig konfiguriert (Schlüssel + mind. ein Subkalender erforderlich).</div>
        @endif

        <form action="{{ route('admin.teamup.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label>
                    <input type="checkbox" name="enabled" value="1" {{ old('enabled', $settings->enabled) ? 'checked' : '' }}>
                    Synchronisation aktivieren
                </label>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Calendar Key (geheim, Prefix <code>ks</code>)</label>
                    <input type="password" name="calendar_key" value="{{ old('calendar_key', $settings->calendar_key ?? '') }}" placeholder="ksXXXXXXXX">
                    <small>Leer lassen, um den gespeicherten Wert zu behalten.</small>
                    @error('calendar_key') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label>API Key (Entwickler-Token)</label>
                    <input type="password" name="api_key" value="{{ old('api_key', $settings->api_key ?? '') }}">
                    @error('api_key') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-group">
                <label>Zeitzone</label>
                <input type="text" name="timezone" value="{{ old('timezone', $settings->timezone ?? 'Europe/Berlin') }}">
            </div>

            <h3 style="font-size:1rem;margin:.75rem 0;">Subkalender-Zuordnung</h3>
            @php
                $typeLabels = [
                    'default'                   => 'Standard (Fallback)',
                    'fix_schedule'              => 'Fixobjekt-Termine',
                    'extra_auftrag'             => 'Extra-Aufträge',
                    'employee_shift'            => 'Schichten',
                    'personal_appointment'      => 'Persönliche Termine',
                    'internal_event'            => 'Interne Termine',
                ];
            @endphp

            <div class="form-row" style="grid-template-columns:1fr 1fr;">
                @foreach($typeLabels as $key => $label)
                    <div class="form-group">
                        <label>{{ $label }}</label>
                        <input type="number" name="subcalendar_{{ $key }}"
                               value="{{ old('subcalendar_' . $key, $settings->subcalendarFor($key) ?? '') }}" placeholder="Subkalender-ID">
                    </div>
                @endforeach
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Speichern</button>
            </div>
        </form>

        <hr style="margin:1.5rem 0;border:none;border-top:1px solid #e2e8f0;">

        <form action="{{ route('admin.teamup.sync') }}" method="POST">
            @csrf
            <div style="display:flex;align-items:center;gap:1rem;justify-content:space-between;">
                <div style="font-size:.85rem;color:#475569;">
                    Letzter Sync: {{ $lastSync?->last_pushed_at?->format('d.m.Y H:i') ?? '—' }}
                </div>
                <button type="submit" class="btn btn-secondary">Jetzt synchronisieren</button>
            </div>
        </form>
    </div>
@endsection