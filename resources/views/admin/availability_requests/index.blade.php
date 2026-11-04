@extends('admin.layouts.app')

@section('title', __('availability.title'))

@section('content')
<h1>{{ __('availability.title') }}</h1>

@if($errors->any())
    <div class="alert alert-danger" role="alert">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="card">
    <div class="card-header">
        <h2 class="card-title">{{ __('availability.pending') }}</h2>
        @if($pendingRequests->total() > 0)
            <span class="badge badge-red">{{ $pendingRequests->total() }}</span>
        @endif
    </div>
    <table class="mobile-card-table">
        <thead>
            <tr>
                <th>{{ __('availability.employee') }}</th>
                <th>{{ __('availability.start') }}</th>
                <th>{{ __('availability.end') }}</th>
                <th>{{ __('availability.address') }}</th>
                <th>{{ __('availability.notes') }}</th>
                <th>{{ __('availability.submitted') }}</th>
                <th>{{ __('availability.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pendingRequests as $availabilityRequest)
                <tr>
                    <td data-label="{{ __('availability.employee') }}">{{ $availabilityRequest->user?->name ?? '—' }}</td>
                    <td data-label="{{ __('availability.start') }}">{{ $availabilityRequest->start_at->format('d.m.Y H:i') }}</td>
                    <td data-label="{{ __('availability.end') }}">{{ $availabilityRequest->end_at->format('d.m.Y H:i') }}</td>
                    <td data-label="{{ __('availability.address') }}">{{ $availabilityRequest->address }}</td>
                    <td data-label="{{ __('availability.notes') }}">{{ $availabilityRequest->notes ?: '—' }}</td>
                    <td data-label="{{ __('availability.submitted') }}">{{ $availabilityRequest->created_at->format('d.m.Y H:i') }}</td>
                    <td data-label="{{ __('availability.actions') }}">
                        <div class="actions">
                            <form action="{{ route('admin.availability-requests.approve', $availabilityRequest) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">{{ __('availability.approve') }}</button>
                            </form>
                            <form action="{{ route('admin.availability-requests.reject', $availabilityRequest) }}" method="POST">
                                @csrf
                                <input type="text" name="admin_note" maxlength="2000" placeholder="{{ __('availability.admin_note') }}" aria-label="{{ __('availability.admin_note') }}">
                                <button type="submit" class="btn btn-danger btn-sm">{{ __('availability.reject') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr class="mobile-table-empty">
                    <td colspan="7" style="text-align:center;color:#94a3b8;padding:2rem">{{ __('availability.empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="pagination">{{ $pendingRequests->links() }}</div>
</div>

<div class="card" style="margin-top:1rem">
    <div class="card-header">
        <h2 class="card-title">{{ __('availability.reviewed') }}</h2>
    </div>
    <table class="mobile-card-table">
        <thead>
            <tr>
                <th>{{ __('availability.employee') }}</th>
                <th>{{ __('availability.start') }}</th>
                <th>{{ __('availability.end') }}</th>
                <th>{{ __('availability.address') }}</th>
                <th>{{ __('availability.status_label') }}</th>
                <th>{{ __('availability.admin_note') }}</th>
                <th>{{ __('availability.reviewed_by') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reviewedRequests as $availabilityRequest)
                <tr>
                    <td data-label="{{ __('availability.employee') }}">{{ $availabilityRequest->user?->name ?? '—' }}</td>
                    <td data-label="{{ __('availability.start') }}">{{ $availabilityRequest->start_at->format('d.m.Y H:i') }}</td>
                    <td data-label="{{ __('availability.end') }}">{{ $availabilityRequest->end_at->format('d.m.Y H:i') }}</td>
                    <td data-label="{{ __('availability.address') }}">{{ $availabilityRequest->address }}</td>
                    <td data-label="{{ __('availability.status_label') }}">
                        <span class="badge {{ $availabilityRequest->status->value === 'approved' ? 'badge-green' : 'badge-red' }}">
                            {{ $availabilityRequest->status->label() }}
                        </span>
                    </td>
                    <td data-label="{{ __('availability.admin_note') }}">{{ $availabilityRequest->admin_note ?: '—' }}</td>
                    <td data-label="{{ __('availability.reviewed_by') }}">{{ $availabilityRequest->reviewer?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr class="mobile-table-empty">
                    <td colspan="7" style="text-align:center;color:#94a3b8;padding:2rem">{{ __('availability.empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="pagination">{{ $reviewedRequests->links() }}</div>
</div>
@endsection
