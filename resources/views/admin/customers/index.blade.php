@extends('admin.layouts.app')
@section('title', __('messages.customers.title'))

@section('content')
<div class="card">
    <div class="card-header">
        <h1 class="card-title">{{ __('messages.customers.title') }}</h1>
        <a href="{{ route('admin.customers.create') }}" class="btn btn-primary">
            + {{ __('messages.customers.create') }}
        </a>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Kontakt</th>
                <th>Standorte</th>
                <th>Status</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            @forelse($customers as $customer)
            <tr>
                <td>{{ $customer->id }}</td>
                <td>
                    <a href="{{ route('admin.customers.show', $customer) }}">{{ $customer->name }}</a>
                </td>
                <td>{{ $customer->contact_person ?? '—' }}</td>
                <td>{{ $customer->locations_count }}</td>
                <td>
                    @if($customer->isActive())
                        <span class="badge badge-green">Aktiv</span>
                    @else
                        <span class="badge badge-red">Inaktiv</span>
                    @endif
                </td>
                <td>
                    <div class="actions">
                        <a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-secondary btn-sm">Bearbeiten</a>
                        <form action="{{ route('admin.customers.toggle-status', $customer) }}" method="POST" style="display:inline">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-secondary btn-sm">
                                {{ $customer->isActive() ? 'Deaktivieren' : 'Aktivieren' }}
                            </button>
                        </form>
                        <form action="{{ route('admin.customers.destroy', $customer) }}" method="POST"
                              onsubmit="return confirm('Wirklich löschen?')" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:2rem">Keine Kunden gefunden.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">{{ $customers->links() }}</div>
</div>
@endsection
