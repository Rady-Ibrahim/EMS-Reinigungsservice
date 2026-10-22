@extends('admin.layouts.app')
@section('title', __('customer.locations') . ' — ' . $customer->name)

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h1>{{ __('customer.locations') }} — {{ $customer->name }}</h1>
    <div class="actions">
        <a href="{{ route('admin.customers.locations.create', $customer) }}" class="btn btn-primary">{{ __('customer.add_location') }}</a>
        <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-secondary">{{ __('customer.cancel') }}</a>
    </div>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>{{ __('locations.name') }}</th>
                <th>{{ __('locations.address') }}</th>
                <th>{{ __('locations.contact') }}</th>
                <th>{{ __('locations.status') }}</th>
                <th>{{ __('customer.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($locations as $loc)
            <tr>
                <td>
                    <strong>{{ $loc->name }}</strong>
                    @if($loc->trashed())<span class="badge badge-red">{{ __('locations.deleted') }}</span>@endif
                </td>
                <td style="color:var(--text-muted)">{{ $loc->fullAddress() }}</td>
                <td style="color:var(--text-muted)">{{ $loc->contact_person ?? '—' }}</td>
                <td>
                    @if($loc->is_active)
                        <span class="badge badge-green">{{ __('locations.active') }}</span>
                    @else
                        <span class="badge badge-gray">{{ __('locations.inactive') }}</span>
                    @endif
                </td>
                <td>
                    <div class="actions">
                        <a href="{{ route('admin.locations.show', $loc) }}" class="btn btn-secondary btn-sm">{{ __('customer.details') }}</a>
                        <a href="{{ route('admin.locations.edit', $loc) }}" class="btn btn-secondary btn-sm">{{ __('customer.edit') }}</a>
                        @if(! $loc->trashed())
                        <form method="POST" action="{{ route('admin.locations.destroy', $loc) }}" onsubmit="return confirm('{{ __('customer.confirm_delete') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">{{ __('customer.delete') }}</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="color:var(--text-faint)">{{ __('customer.no_locations') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">{{ $locations->links() }}</div>
</div>
@endsection
