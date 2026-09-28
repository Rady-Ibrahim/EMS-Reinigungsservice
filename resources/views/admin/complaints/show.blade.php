@extends('admin.layouts.app')
@section('title', $complaint->title)

@section('content')
<div class="card" style="margin-bottom:1.5rem">
    <div class="card-header">
        <h1 class="card-title">{{ $complaint->title }}</h1>
        <span class="badge {{ $complaint->status->badgeClass() }}">{{ $complaint->status->label() }}</span>
    </div>

    <table>
        <tbody>
            <tr><th style="width:200px">{{ __('messages.cal.location') }}</th>
                <td>{{ $complaint->location?->name ?? '—' }}</td></tr>
            <tr><th>{{ __('messages.cal.customer') }}</th>
                <td>{{ $complaint->customer?->name ?? '—' }}</td></tr>
            <tr><th>{{ __('messages.cal.staff') }}</th>
                <td>{{ $complaint->assignedEmployee?->name ?? '—' }}</td></tr>
            <tr><th>{{ __('complaints.reported_by') }}</th>
                <td>{{ $complaint->reported_by ?? '—' }}</td></tr>
            <tr><th>{{ __('cal_page.reason') }}</th>
                <td>{{ $complaint->occurred_at?->format('d.m.Y H:i') ?? '—' }}</td></tr>
            <tr><th>{{ __('messages.cal.description') }}</th>
                <td style="white-space:pre-wrap">{{ $complaint->description }}</td></tr>
            @if($complaint->resolution)
                <tr><th>{{ __('validation.attributes.resolution') }}</th>
                    <td style="white-space:pre-wrap">{{ $complaint->resolution }}</td></tr>
            @endif
            @if($complaint->hasPhotos())
                <tr><th>📷</th>
                    <td>
                        @foreach($complaint->photos as $photo)
                            <span class="badge badge-gray" style="font-size:.7rem">{{ basename($photo) }}</span>
                        @endforeach
                    </td></tr>
            @endif
        </tbody>
    </table>
</div>

{{-- Status transition --}}
<div class="card" style="margin-bottom:1.5rem">
    <div class="card-header"><span class="card-title">{{ __('messages.dash.complaints') }}</span></div>
    <form method="POST" action="{{ route('admin.complaints.status.update', $complaint) }}" style="padding:1rem">
        @csrf
        @method('PATCH')

        <div class="form-group">
            <label>{{ __('messages.cal.status') }}</label>
            <select name="status" class="form-control" required>
                @foreach(\App\Enums\ComplaintStatusEnum::cases() as $s)
                    <option value="{{ $s->value }}" @selected($complaint->status === $s)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label>{{ __('validation.attributes.resolution') }}</label>
            <textarea name="resolution" rows="3" class="form-control">{{ $complaint->resolution }}</textarea>
        </div>

        <div class="form-group">
            <label>{{ __('cal_page.justification') }}</label>
            <input type="text" name="reason" class="form-control" maxlength="255">
        </div>

        <button type="submit" class="btn btn-primary">{{ __('messages.save') }}</button>
        <a href="{{ route('admin.complaints.index') }}" class="btn btn-secondary">{{ __('report.back') }}</a>
    </form>
</div>

{{-- Assignment --}}
<div class="card">
    <div class="card-header"><span class="card-title">{{ __('cal_page.reassign') }}</span></div>
    <form method="POST" action="{{ route('admin.complaints.assign', $complaint) }}" style="padding:1rem">
        @csrf
        @method('PATCH')
        <select name="assigned_employee_id" class="form-control" required>
            <option value="—">— {{ __('cal_page.select_placeholder') }} —</option>
            @foreach(\App\Models\User::orderBy('name')->get() as $u)
                <option value="{{ $u->id }}" @selected($complaint->assigned_employee_id === $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary" style="margin-top:.5rem">{{ __('messages.save') }}</button>
    </form>
</div>
@endsection
