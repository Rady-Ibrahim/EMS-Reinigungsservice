@extends('admin.layouts.app')
@section('title', __('extra_order.page_title_index'))

@section('content')
<div class="card">
    <div class="card-header">
        <h1 class="card-title">{{ __('extra_order.page_title_index') }}</h1>
        <a href="{{ route('admin.extra-auftraege.create') }}" class="btn btn-primary">{{ __('extra_order.btn_new_order') }}</a>
    </div>

    <table class="mobile-card-table">
        <thead>
            <tr>
                <th>{{ __('extra_order.th_title') }}</th>
                <th>{{ __('extra_order.th_customer_location') }}</th>
                <th>{{ __('extra_order.label_type') }}</th>
                <th>{{ __('extra_order.th_date') }}</th>
                <th>{{ __('extra_order.leader') }}</th>
                <th>{{ __('extra_order.th_team') }}</th>
                <th>{{ __('extra_order.th_travel_paid') }}</th>
                <th>{{ __('extra_order.th_status') }}</th>
                <th>{{ __('extra_order.th_actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
            <tr>
                <td data-label="{{ __('extra_order.th_title') }}"><a href="{{ route('admin.extra-auftraege.show', $order) }}">{{ $order->title }}</a></td>
                <td data-label="{{ __('extra_order.th_customer_location') }}">
                    <small style="color:#64748b">{{ $order->customer->name }}</small><br>
                    <small>{{ $order->location->name }}</small>
                </td>
                <td data-label="{{ __('extra_order.label_type') }}"><span class="badge badge-gray">{{ $order->order_type->label() }}</span></td>
                <td data-label="{{ __('extra_order.th_date') }}">{{ $order->scheduled_date->format('d.m.Y') }}</td>
                <td data-label="{{ __('extra_order.leader') }}">{{ $order->leader?->user?->name ?? '—' }}</td>
                <td data-label="{{ __('extra_order.th_team') }}">{{ $order->assignees_count }}</td>
                <td data-label="{{ __('extra_order.th_travel_paid') }}">
                    @if($order->is_travel_time_paid)
                        <span class="badge badge-green">{{ __('extra_order.yes') }}</span>
                    @else
                        <span class="badge badge-gray">{{ __('extra_order.no') }}</span>
                    @endif
                </td>
                <td data-label="{{ __('extra_order.th_status') }}">
                    <span class="badge {{ $order->status->badgeClass() }}">
                        {{ $order->status->label() }}
                    </span>
                </td>
                <td data-label="{{ __('extra_order.th_actions') }}">
                    <div class="actions">
                        <a href="{{ route('admin.extra-auftraege.edit', $order) }}" class="btn btn-secondary btn-sm">{{ __('extra_order.btn_edit') }}</a>
                        @if(!$order->status->isTerminal())
                        <form action="{{ route('admin.extra-auftraege.cancel', $order) }}" method="POST"
                              onsubmit="return confirm('{{ __('extra_order.confirm_cancel') }}')" style="display:inline">
                            @csrf
                            <input type="hidden" name="reason" value="Admin storniert">
                            <button class="btn btn-danger btn-sm">{{ __('extra_order.btn_cancel_order') }}</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr class="mobile-table-empty"><td colspan="9" style="text-align:center;color:#94a3b8;padding:2rem">{{ __('extra_order.empty_orders') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">{{ $orders->links() }}</div>
</div>
@endsection
