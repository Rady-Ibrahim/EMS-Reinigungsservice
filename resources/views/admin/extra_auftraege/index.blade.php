@extends('admin.layouts.app')
@section('title', 'Extra-Aufträge')

@section('content')
<div class="card">
    <div class="card-header">
        <h1 class="card-title">Extra-Aufträge</h1>
        <a href="{{ route('admin.extra-auftraege.create') }}" class="btn btn-primary">+ Neuer Auftrag</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Titel</th>
                <th>Kunde / Standort</th>
                <th>Typ</th>
                <th>Datum</th>
                <th>Vorarbeiter</th>
                <th>Team</th>
                <th>Anfahrt bezahlt</th>
                <th>Status</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
            <tr>
                <td><a href="{{ route('admin.extra-auftraege.show', $order) }}">{{ $order->title }}</a></td>
                <td>
                    <small style="color:#64748b">{{ $order->customer->name }}</small><br>
                    <small>{{ $order->location->name }}</small>
                </td>
                <td><span class="badge badge-gray">{{ $order->order_type->label() }}</span></td>
                <td>{{ $order->scheduled_date->format('d.m.Y') }}</td>
                <td>{{ $order->leader?->user?->name ?? '—' }}</td>
                <td>{{ $order->assignees_count }}</td>
                <td>
                    @if($order->is_travel_time_paid)
                        <span class="badge badge-green">Ja</span>
                    @else
                        <span class="badge badge-gray">Nein</span>
                    @endif
                </td>
                <td>
                    <span class="badge {{ $order->status->badgeClass() }}">
                        {{ $order->status->label() }}
                    </span>
                </td>
                <td>
                    <div class="actions">
                        <a href="{{ route('admin.extra-auftraege.edit', $order) }}" class="btn btn-secondary btn-sm">Bearbeiten</a>
                        @if(!$order->status->isTerminal())
                        <form action="{{ route('admin.extra-auftraege.cancel', $order) }}" method="POST"
                              onsubmit="return confirm('Auftrag stornieren?')" style="display:inline">
                            @csrf
                            <input type="hidden" name="reason" value="Admin storniert">
                            <button class="btn btn-danger btn-sm">Stornieren</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="9" style="text-align:center;color:#94a3b8;padding:2rem">Keine Aufträge gefunden.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">{{ $orders->links() }}</div>
</div>
@endsection
