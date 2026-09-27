@extends('admin.layouts.app')
@section('title', $extraAuftrag->title)

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h1>{{ $extraAuftrag->title }}
        <span class="badge {{ $extraAuftrag->status->badgeClass() }}" style="font-size:.8rem;margin-left:.5rem">
            {{ $extraAuftrag->status->label() }}
        </span>
    </h1>
    <div class="actions">
        @if(!$extraAuftrag->status->isTerminal())
        <a href="{{ route('admin.extra-auftraege.edit', $extraAuftrag) }}" class="btn btn-secondary">{{ __('extra_order.btn_edit') }}</a>
        @endif
        @if($extraAuftrag->status->value === 'completed')
        <form method="GET" action="javascript:void(0)" id="reopen-extra-form" style="display:inline;">
            @csrf
            <button type="button" class="btn btn-danger" onclick="document.getElementById('reopen-modal').style.display='block'">{{ __('extra_order.btn_reopen') }}</button>
        </form>
        @endif
    </div>
</div>

@if($extraAuftrag->status->value === 'completed')
{{-- Reopen confirmation modal --}}
<div id="reopen-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:50;">
    <div style="background:#fff;border-radius:10px;padding:1.5rem;max-width:440px;width:90%;margin:auto;margin-top:10vh;">
        <h2 class="card-title" style="margin-bottom:.75rem">{{ __('extra_order.modal_reopen_title') }}</h2>
        <p style="font-size:.875rem;color:#475569;margin-bottom:1rem;">
            {!! __('extra_order.modal_reopen_text') !!}
        </p>
        <form method="POST" action="{{ route('admin.extra-auftraege.reopen', $extraAuftrag) }}">
            @csrf
            <div class="form-group">
                <label for="reopen_reason">{{ __('extra_order.label_reason') }}</label>
                <textarea id="reopen_reason" name="reopen_reason" rows="3" required></textarea>
                @error('reopen_reason')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-danger">{{ __('extra_order.btn_confirm') }}</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('reopen-modal').style.display='none'">{{ __('extra_order.btn_cancel') }}</button>
            </div>
        </form>
    </div>
</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
    {{-- Order Details --}}
    <div class="card">
        <div class="card-title" style="margin-bottom:.75rem">{{ __('extra_order.order_data') }}</div>
        <table>
            <tr><td style="color:#64748b;width:150px">{{ __('extra_order.label_customer_plain') }}</td><td>{{ $extraAuftrag->customer->name }}</td></tr>
            <tr><td style="color:#64748b">{{ __('extra_order.label_location_plain') }}</td><td>{{ $extraAuftrag->location->name }}</td></tr>
            <tr><td style="color:#64748b">{{ __('extra_order.label_type') }}</td><td>{{ $extraAuftrag->order_type->label() }}</td></tr>
            <tr><td style="color:#64748b">{{ __('extra_order.th_date') }}</td><td>{{ $extraAuftrag->scheduled_date->format('d.m.Y') }}
                @if($extraAuftrag->scheduled_time_start) {{ __('extra_order.time_at', ['time' => substr($extraAuftrag->scheduled_time_start,0,5)]) }} @endif
            </td></tr>
            <tr><td style="color:#64748b">{{ __('extra_order.th_est_short') }}</td><td>{{ $extraAuftrag->estimated_hours ?? '—' }}{{ __('extra_order.hours_abbr') }}</td></tr>
            <tr><td style="color:#64748b">{{ __('extra_order.th_travel_paid') }}</td><td>
                @if($extraAuftrag->is_travel_time_paid)
                    <span class="badge badge-green">{{ __('extra_order.yes') }}</span>
                @else
                    <span class="badge badge-gray">{{ __('extra_order.no') }}</span>
                @endif
            </td></tr>
            <tr><td style="color:#64748b">{{ __('extra_order.created_by') }}</td><td>{{ $extraAuftrag->creator->name }}</td></tr>
        </table>
        @if($extraAuftrag->description)
        <div style="margin-top:.75rem;padding:.65rem;background:#f8fafc;border-radius:6px;font-size:.85rem">
            {{ $extraAuftrag->description }}
        </div>
        @endif
        @if($extraAuftrag->price)
        <div style="margin-top:.75rem;padding:.65rem;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;font-size:.85rem">
            💰 {{ __('extra_order.price_label') }}: <strong>{{ number_format((float)$extraAuftrag->price, 2) }} €</strong>
            @if($extraAuftrag->internal_cost)
            | {{ __('extra_order.cost_label') }}: {{ number_format((float)$extraAuftrag->internal_cost, 2) }} €
            @endif
        </div>
        @endif
    </div>

    {{-- Team --}}
    <div class="card">
        <div class="card-title" style="margin-bottom:.75rem">{{ __('extra_order.th_team') }} ({{ $extraAuftrag->assignees->count() }})</div>
        @forelse($extraAuftrag->assignees as $assignee)
        <div style="padding:.5rem 0;border-bottom:1px solid #f1f5f9">
            <span class="color-dot" style="background:{{ $assignee->user->employeeProfile?->calendar_color ?? '#ccc' }}"></span>
            <strong>{{ $assignee->user->name }}</strong>
            @if($assignee->isLeader())
                <span class="badge badge-blue" style="font-size:.7rem;margin-left:.35rem">{{ __('extra_order.leader') }}</span>
            @endif
            {{-- Execution summary --}}
            @php $exec = $extraAuftrag->executions->firstWhere('user_id', $assignee->user_id) @endphp
            @if($exec)
            <small style="color:#64748b;display:block;margin-left:20px">
                {{ __('extra_order.th_status') }}: {{ $exec->status->label() }}
                @if($exec->paid_minutes) | {{ __('extra_order.label_paid') }}: {{ round($exec->paid_minutes/60, 2) }}{{ __('extra_order.hours_abbr') }} @endif
            </small>
            @endif
        </div>
        @empty
        <p style="color:#94a3b8;font-size:.85rem">{{ __('extra_order.empty_team') }}</p>
        @endforelse
    </div>
</div>

{{-- Checklist Template --}}
@if($extraAuftrag->checklist_template)
<div class="card">
    <div class="card-title" style="margin-bottom:.75rem">{{ __('extra_order.section_checklist') }} ({{ count($extraAuftrag->checklist_template) }} {{ __('extra_order.points') }})</div>
    <ul style="list-style:none;padding:0">
        @foreach($extraAuftrag->checklist_template as $item)
        <li style="padding:.35rem 0;border-bottom:1px solid #f1f5f9;font-size:.875rem">
            <span style="margin-right:.5rem">☐</span> {{ $item['task'] }}
        </li>
        @endforeach
    </ul>
</div>
@endif

{{-- Travel Tracks --}}
@if($extraAuftrag->travelTracks->count())
<div class="card">
    <div class="card-title" style="margin-bottom:.75rem">{{ __('extra_order.section_travel') }}</div>
    <table>
        <thead><tr><th>{{ __('extra_order.employee') }}</th><th>{{ __('extra_order.th_departure') }}</th><th>{{ __('extra_order.th_arrival') }}</th><th>{{ __('extra_order.th_travel_time') }}</th><th>{{ __('extra_order.label_paid') }}</th></tr></thead>
        <tbody>
            @foreach($extraAuftrag->travelTracks as $track)
            <tr>
                <td>{{ $track->employee->name }}</td>
                <td>{{ $track->departure_at->format('H:i') }}</td>
                <td>{{ $track->arrival_at?->format('H:i') ?? '—' }}</td>
                <td>{{ $track->travel_minutes ? $track->travel_minutes . ' ' . __('extra_order.minutes_abbr') : '—' }}</td>
                <td>
                    @if($track->is_paid)
                        <span class="badge badge-green">{{ __('extra_order.yes') }}</span>
                    @else
                        <span class="badge badge-gray">{{ __('extra_order.no') }}</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
