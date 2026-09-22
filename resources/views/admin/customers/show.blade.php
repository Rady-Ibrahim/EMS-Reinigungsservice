@extends('admin.layouts.app')
@section('title', $customer->name)

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h1>{{ $customer->name }}</h1>
    <div class="actions">
        <a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-secondary">Bearbeiten</a>
        <a href="{{ route('admin.customers.locations.create', $customer) }}" class="btn btn-primary">+ Standort</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
    <div class="card">
        <div class="card-title" style="margin-bottom:.75rem">Kundendaten</div>
        <table>
            <tr><td style="color:#64748b;width:140px">E-Mail</td><td>{{ $customer->email ?? '—' }}</td></tr>
            <tr><td style="color:#64748b">Telefon</td><td>{{ $customer->phone ?? '—' }}</td></tr>
            <tr><td style="color:#64748b">Kontakt</td><td>{{ $customer->contact_person ?? '—' }}</td></tr>
            <tr><td style="color:#64748b">Status</td><td>
                @if($customer->isActive())
                    <span class="badge badge-green">Aktiv</span>
                @else
                    <span class="badge badge-red">Inaktiv</span>
                @endif
            </td></tr>
            <tr><td style="color:#64748b">Portal</td><td>{{ $customer->portal_access ? 'Ja' : 'Nein' }}</td></tr>
        </table>
        @if($customer->notes)
        <div style="margin-top:1rem;padding:.75rem;background:#f8fafc;border-radius:6px;font-size:.85rem">
            <strong>Notizen:</strong><br>{{ $customer->notes }}
        </div>
        @endif
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Standorte ({{ $customer->locations->count() }})</span>
        </div>
        @forelse($customer->locations as $loc)
        <div style="padding:.5rem 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
            <div>
                <strong>{{ $loc->name }}</strong><br>
                <small style="color:#64748b">{{ $loc->fullAddress() }}</small>
            </div>
            <div class="actions">
                <a href="{{ route('locations.show', $loc) }}" class="btn btn-secondary btn-sm">Details</a>
            </div>
        </div>
        @empty
        <p style="color:#94a3b8;font-size:.85rem">Noch keine Standorte.</p>
        @endforelse
    </div>
</div>
@endsection
