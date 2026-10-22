@extends('admin.layouts.app')
@section('title', __('time_page.show_title'))

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h1>{{ __('time_page.show_title') }} #{{ $timeAdjustment->id }}</h1>
    <a href="{{ route('admin.time-adjustments.index') }}" class="btn btn-secondary">← {{ __('time_page.back') }}</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
    <div class="card">
        <div class="card-title" style="margin-bottom:.75rem">{{ __('time_page.request_data') }}</div>
        <table>
            <tr><td style="color:#64748b;width:160px">{{ __('time_page.employee') }}</td><td><strong>{{ $timeAdjustment->employee->name }}</strong></td></tr>
            <tr><td style="color:#64748b">{{ __('time_page.job_type') }}</td>
                <td><span class="badge badge-gray">{{ $timeAdjustment->job_type === 'fix_object' ? __('time_page.fix_object') : ($timeAdjustment->job_type === 'employee_shift' ? __('time_page.shift_overtime') : __('time_page.extra_assignment')) }}</span></td>
            </tr>
            <tr><td style="color:#64748b">{{ __('time_page.job_id') }}</td><td>#{{ $timeAdjustment->job_id }}</td></tr>
            <tr><td style="color:#64748b">{{ __('time_page.status') }}</td>
                <td>
                    @php $s = $timeAdjustment->status->value @endphp
                    <span class="badge {{ $s === 'approved' ? 'badge-green' : ($s === 'rejected' ? 'badge-red' : 'badge-gray') }}">
                        {{ $timeAdjustment->status->label() }}
                    </span>
                </td>
            </tr>
            <tr><td style="color:#64748b">{{ __('time_page.submitted') }}</td>
                <td>{{ $timeAdjustment->client_submitted_at?->format('d.m.Y H:i') ?? $timeAdjustment->created_at->format('d.m.Y H:i') }}</td>
            </tr>
        </table>
    </div>

    <div class="card">
        <div class="card-title" style="margin-bottom:.75rem">{{ __('time_page.time_comparison') }}</div>
        <table>
            <tr>
                <th style="text-align:left;font-weight:600;color:#374151;padding-bottom:.5rem">{{ __('time_page.original_immutable') }}</th>
                <th style="text-align:left;font-weight:600;color:#374151;padding-bottom:.5rem">{{ __('time_page.requested') }}</th>
            </tr>
            @if($timeAdjustment->job_type === 'employee_shift')
                <tr>
                    <td style="color:#64748b">{{ __('time_page.requested_extra_hours') }}</td>
                    <td><strong>+{{ number_format((float) $timeAdjustment->requested_extra_hours, 2, ',', '.') }} h</strong></td>
                </tr>
                @if($timeAdjustment->shift)
                    <tr>
                        <td style="color:#64748b">{{ __('time_page.shift') }}</td>
                        <td>{{ $timeAdjustment->shift->title }} ({{ number_format($timeAdjustment->shift->scheduledWorkHours(), 2, ',', '.') }} h)</td>
                    </tr>
                @endif
            @endif
            <tr>
                <td style="color:#94a3b8;font-size:.875rem">
                    {{ __('time_page.start') }} {{ $timeAdjustment->original_start?->format('d.m.Y H:i') ?? '—' }}<br>
                    {{ __('time_page.end') }} {{ $timeAdjustment->original_end?->format('H:i') ?? '—' }}
                </td>
                <td style="font-size:.875rem">
                    {{ __('time_page.start') }} <strong>{{ $timeAdjustment->requested_start->format('d.m.Y H:i') }}</strong><br>
                    {{ __('time_page.end') }} <strong>{{ $timeAdjustment->requested_end->format('H:i') }}</strong>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="card" style="margin-top:1rem">
    <div class="card-title" style="margin-bottom:.75rem">{{ __('time_page.justification') }}</div>
    <p style="font-size:.9rem;line-height:1.6">{{ $timeAdjustment->reason }}</p>
</div>

@if($timeAdjustment->admin_note)
<div class="card" style="margin-top:1rem">
    <div class="card-title" style="margin-bottom:.5rem">{{ __('time_page.admin_note') }}</div>
    <p style="font-size:.875rem;color:#475569">{{ $timeAdjustment->admin_note }}</p>
    @if($timeAdjustment->reviewer)
    <small style="color:#94a3b8">{{ __('time_page.edited_by') }} {{ $timeAdjustment->reviewer->name }} {{ __('time_page.on') }} {{ $timeAdjustment->reviewed_at?->format('d.m.Y H:i') }}</small>
    @endif
</div>
@endif

@if($timeAdjustment->isPending())
<div class="card" style="margin-top:1rem;border:1px solid #e2e8f0">
    <div class="card-title" style="margin-bottom:.75rem">{{ __('time_page.decision') }}</div>
    <div style="display:flex;gap:1rem;flex-wrap:wrap">
        {{-- Approve --}}
        <form action="{{ route('admin.time-adjustments.approve', $timeAdjustment) }}" method="POST" style="flex:1">
            @csrf
            <div class="form-group">
                <label>{{ __('time_page.note_optional') }}</label>
                <textarea name="admin_note" rows="2" placeholder="{{ __('time_page.note_placeholder') }}"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" onclick="return confirm('{{ __('time_page.approve_apply_confirm') }}')">
                ✓ {{ __('time_page.approve_apply') }}
            </button>
        </form>

        {{-- Reject --}}
        <form action="{{ route('admin.time-adjustments.reject', $timeAdjustment) }}" method="POST" style="flex:1">
            @csrf
            <div class="form-group">
                <label>{{ __('time_page.reject_reason_required') }}</label>
                <textarea name="admin_note" rows="2" required placeholder="{{ __('time_page.reject_reason_placeholder') }}"></textarea>
                @error('admin_note')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn btn-danger">✗ {{ __('time_page.reject') }}</button>
        </form>
    </div>
</div>
@endif
@endsection
