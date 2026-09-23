@extends('admin.layouts.app')

@section('title', $appointment->exists ? 'Termin bearbeiten' : 'Neuer Termin')
@section('content')
    <h1>{{ $appointment->exists ? 'Termin bearbeiten' : 'Neuer persönlicher Termin' }}</h1>

    <div class="card" style="max-width:720px;">
        <form action="{{ $appointment->exists ? route('admin.calendar.appointments.update', $appointment) : route('admin.calendar.appointments.store') }}" method="POST">
            @csrf
            @if($appointment->exists) @method('PUT') @endif

            <div class="form-group">
                <label>Benutzer *</label>
                <select name="user_id" required>
                    <option value="">— auswählen —</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ old('user_id', $appointment->user_id) == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
                @error('user_id') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label>Titel *</label>
                <input type="text" name="title" value="{{ old('title', $appointment->title) }}" required>
                @error('title') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Beginn *</label>
                    <input type="datetime-local" name="start_at"
                           value="{{ old('start_at', $appointment->start_at?->format('Y-m-d\TH:i')) }}" required>
                    @error('start_at') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label>Ende *</label>
                    <input type="datetime-local" name="end_at"
                           value="{{ old('end_at', $appointment->end_at?->format('Y-m-d\TH:i')) }}" required>
                    @error('end_at') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Ganztägig</label>
                    <input type="checkbox" name="all_day" value="1" {{ old('all_day', $appointment->all_day) ? 'checked' : '' }}>
                </div>
                <div class="form-group">
                    <label>Ort</label>
                    <input type="text" name="location" value="{{ old('location', $appointment->location) }}">
                </div>
            </div>

            <div class="form-group">
                <label>Farbe</label>
                <input type="color" name="color" value="{{ old('color', $appointment->color ?? '#8b5cf6') }}">
            </div>

            @include('admin.calendar.partials.force_checkbox')

            <div class="actions">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="{{ route('admin.calendar.appointments.index') }}" class="btn btn-secondary">Abbrechen</a>
            </div>
        </form>
    </div>
@endsection