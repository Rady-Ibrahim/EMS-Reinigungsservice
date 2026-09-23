@extends('admin.layouts.app')

@section('title', $shift->exists ? 'Schicht bearbeiten' : 'Neue Schicht')
@section('content')
    <h1>{{ $shift->exists ? 'Schicht bearbeiten' : 'Neue Schicht' }}</h1>

    <div class="card" style="max-width:720px;">
        <form action="{{ $shift->exists ? route('admin.calendar.shifts.update', $shift) : route('admin.calendar.shifts.store') }}" method="POST">
            @csrf
            @if($shift->exists) @method('PUT') @endif

            <div class="form-group">
                <label>Mitarbeiter *</label>
                <select name="user_id" required>
                    <option value="">— auswählen —</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" {{ old('user_id', $shift->user_id) == $employee->id ? 'selected' : '' }}>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
                @error('user_id') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label>Titel *</label>
                <input type="text" name="title" value="{{ old('title', $shift->title) }}" required>
                @error('title') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Beginn *</label>
                    <input type="datetime-local" name="start_at"
                           value="{{ old('start_at', $shift->start_at?->format('Y-m-d\TH:i')) }}" required>
                    @error('start_at') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label>Ende *</label>
                    <input type="datetime-local" name="end_at"
                           value="{{ old('end_at', $shift->end_at?->format('Y-m-d\TH:i')) }}" required>
                    @error('end_at') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Ganztägig</label>
                    <input type="checkbox" name="all_day" value="1" {{ old('all_day', $shift->all_day) ? 'checked' : '' }}>
                </div>
                <div class="form-group">
                    <label>Farbe</label>
                    <input type="color" name="color" value="{{ old('color', $shift->color ?? '#10b981') }}">
                </div>
            </div>

            <div class="form-group">
                <label>Notizen</label>
                <textarea name="notes" rows="3">{{ old('notes', $shift->notes) }}</textarea>
            </div>

            @include('admin.calendar.partials.force_checkbox')

            <div class="actions">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="{{ route('admin.calendar.shifts.index') }}" class="btn btn-secondary">Abbrechen</a>
            </div>
        </form>
    </div>
@endsection