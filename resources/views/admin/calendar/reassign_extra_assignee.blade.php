@extends('admin.layouts.app')

@section('title', 'Team-Mitglied tauschen')
@section('content')
    <h1>Mitarbeiter im Extra-Auftrag tauschen</h1>

    <div class="card" style="max-width:720px;">
        <div style="margin-bottom:1rem;font-size:.875rem;">
            <strong>{{ $assignee->extraAuftrag?->title }}</strong>
            <span class="badge badge-blue">{{ $assignee->extraAuftrag?->scheduled_date?->format('d.m.Y') }}</span>
            <div>Aktuell: <strong>{{ $assignee->user?->name }}</strong> ({{ $assignee->role_in_order->label() }})</div>
            @error('conflicts') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <form action="{{ route('admin.extra-auftraege.assignees.reassign.store', $assignee) }}" method="POST">
            @csrf

            <div class="form-group">
                <label>Neuer Mitarbeiter *</label>
                <select name="user_id" required>
                    <option value="">— auswählen —</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" {{ old('user_id') == $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>
                    @endforeach
                </select>
                @error('user_id') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            @include('admin.calendar.partials.force_checkbox')

            <div class="actions">
                <button type="submit" class="btn btn-primary">Tauschen</button>
                <a href="{{ route('admin.extra-auftraege.show', $assignee->extra_auftrag_id) }}" class="btn btn-secondary">Abbrechen</a>
            </div>
        </form>
    </div>
@endsection