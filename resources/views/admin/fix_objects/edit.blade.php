@extends('admin.layouts.app')
@section('title', 'Fixobjekt bearbeiten')

@section('content')
<h1>Fixobjekt bearbeiten: {{ $fixObject->title }}</h1>

<div class="card">
    <form action="{{ route('admin.fix-objects.update', $fixObject) }}" method="POST">
        @csrf @method('PUT')

        <div class="form-row">
            <div class="form-group">
                <label>Titel *</label>
                <input type="text" name="title" value="{{ old('title', $fixObject->title) }}" required>
            </div>
            <div class="form-group">
                <label>Kalenderfarbe</label>
                <input type="text" name="calendar_color" value="{{ old('calendar_color', $fixObject->calendar_color) }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Frequenz *</label>
                <select name="frequency" required>
                    @foreach(\App\Enums\FixFrequencyEnum::cases() as $freq)
                    <option value="{{ $freq->value }}" {{ old('frequency', $fixObject->frequency->value) === $freq->value ? 'selected' : '' }}>
                        {{ $freq->label() }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Vertragsstunden *</label>
                <input type="number" name="contract_hours" step="0.25" min="0.25" max="24"
                       value="{{ old('contract_hours', $fixObject->contract_hours) }}" required>
            </div>
        </div>

        <div class="form-group">
            <label>Wochentage</label>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.25rem">
                @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)
                <label style="display:flex;align-items:center;gap:.3rem;font-weight:normal;cursor:pointer">
                    <input type="checkbox" name="frequency_days[]" value="{{ $day }}"
                           {{ in_array($day, old('frequency_days', $fixObject->frequency_days ?? [])) ? 'checked' : '' }}>
                    {{ $day }}
                </label>
                @endforeach
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Uhrzeit Start</label>
                <input type="time" name="time_start" value="{{ old('time_start', $fixObject->time_start ? substr($fixObject->time_start, 0, 5) : '') }}">
            </div>
            <div class="form-group">
                <label>Uhrzeit Ende</label>
                <input type="time" name="time_end" value="{{ old('time_end', $fixObject->time_end ? substr($fixObject->time_end, 0, 5) : '') }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Gültig ab *</label>
                <input type="date" name="valid_from" value="{{ old('valid_from', $fixObject->valid_from->toDateString()) }}" required>
            </div>
            <div class="form-group">
                <label>Gültig bis</label>
                <input type="date" name="valid_until" value="{{ old('valid_until', $fixObject->valid_until?->toDateString()) }}">
            </div>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="is_active">
                <option value="1" {{ old('is_active', $fixObject->is_active) ? 'selected' : '' }}>Aktiv</option>
                <option value="0" {{ !old('is_active', $fixObject->is_active) ? 'selected' : '' }}>Inaktiv</option>
            </select>
        </div>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:1rem 0">
        <h2 style="font-size:1rem;margin-bottom:.75rem;color:#475569">💰 Finanzdaten</h2>
        <div class="form-row">
            <div class="form-group">
                <label>Preis / Monat (€)</label>
                <input type="number" name="price_per_month" step="0.01" min="0"
                       value="{{ old('price_per_month', $fixObject->price_per_month) }}">
            </div>
            <div class="form-group">
                <label>Preis / Stunde (€)</label>
                <input type="number" name="price_per_hour" step="0.01" min="0"
                       value="{{ old('price_per_hour', $fixObject->price_per_hour) }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Interne Kosten (€)</label>
                <input type="number" name="internal_cost" step="0.01" min="0"
                       value="{{ old('internal_cost', $fixObject->internal_cost) }}">
            </div>
            <div class="form-group">
                <label>Gewinnmarge</label>
                <input type="number" name="profit_margin" step="0.01"
                       value="{{ old('profit_margin', $fixObject->profit_margin) }}">
            </div>
        </div>
        <div class="form-group">
            <label>Interne Notizen</label>
            <textarea name="internal_notes" rows="3">{{ old('internal_notes', $fixObject->internal_notes) }}</textarea>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:.5rem">
            <button type="submit" class="btn btn-primary">Speichern</button>
            <a href="{{ route('admin.fix-objects.show', $fixObject) }}" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
@endsection
