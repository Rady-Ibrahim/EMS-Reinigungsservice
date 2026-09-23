@extends('admin.layouts.app')

@section('title', 'Einzeltermin umweisen')
@section('content')
    <h1>Einzeltermin neu zuweisen</h1>

    <div class="card" style="max-width:720px;">
        <div style="margin-bottom:1rem;font-size:.875rem;">
            <strong>{{ $schedule->fixObject->title }}</strong>
            <span class="badge badge-yellow">{{ $schedule->scheduled_date->format('d.m.Y') }}</span>
            <span class="badge badge-gray">{{ $schedule->scheduled_start ? substr($schedule->scheduled_start,0,5).' – '.substr($schedule->scheduled_end,0,5) : 'Ganztägig' }}</span>
            <div style="margin-top:.4rem;">
                @foreach($fixObject->assignments as $assignment)
                    <span class="badge badge-blue">{{ $assignment->user?->name }} (Vertrag)</span>
                @endforeach
                @foreach($schedule->scheduleAssignments as $override)
                    <span class="badge badge-green">{{ $override->user?->name }} (Override)</span>
                @endforeach
            </div>
        </div>

        <form action="{{ route('admin.calendar.schedules.reassign.store', $schedule) }}" method="POST">
            @csrf

            <div class="form-group">
                <label>Mitarbeiter für diesen Tag *</label>
                <select name="user_id" required>
                    <option value="">— auswählen —</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" {{ old('user_id') == $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>
                    @endforeach
                </select>
                @error('user_id') <div class="field-error">{{ $message }}</div> @enderror
                @error('conflicts') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label>Grund</label>
                <textarea name="reason" rows="2">{{ old('reason') }}</textarea>
            </div>

            @include('admin.calendar.partials.force_checkbox')

            <div class="actions">
                <button type="submit" class="btn btn-primary">Umweisen</button>
                <a href="{{ route('admin.calendar.index') }}" class="btn btn-secondary">Abbrechen</a>
            </div>
        </form>

        @if($schedule->isCompleted())
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:1.5rem 0;">
        <div>
            <h2 class="card-title" style="margin-bottom:.5rem">Termin wieder öffnen</h2>
            <p style="font-size:.85rem;color:#475569;margin-bottom:.75rem;">
                Setzt den Termin zurück auf <strong>In Bearbeitung</strong>. Die letzte Ausführung wird
                auf die Nachher-Fotos-Stufe zurückgesetzt (Begründung wird im Audit-Protokoll erfasst).
            </p>
            <form method="POST" action="{{ route('admin.calendar.schedules.reopen', $schedule) }}">
                @csrf
                <div class="form-group">
                    <label for="reopen_reason">Begründung *</label>
                    <textarea id="reopen_reason" name="reopen_reason" rows="2" required>{{ old('reopen_reason') }}</textarea>
                    @error('reopen_reason')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-danger">Termin wieder öffnen</button>
            </form>
        </div>
        @endif
    </div>
@endsection