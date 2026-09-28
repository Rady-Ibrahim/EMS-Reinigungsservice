@extends('admin.layouts.app')
@section('title', __('complaints.status.pending').' / '.__('messages.nav.reklamations'))

@section('content')
<div class="card" style="margin-bottom:1.5rem">
    <div class="card-header">
        <h1 class="card-title">
            {{ __('messages.nav.reklamations') }}
            @if($openCount > 0)
                <span class="badge badge-red" style="font-size:.8rem;margin-left:.5rem">{{ $openCount }}</span>
            @endif
        </h1>
        <a href="{{ route('admin.complaints.create') }}" class="btn btn-primary btn-sm">+ {{ __('complaints.flash.created') }}</a>
    </div>

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('admin.complaints.index') }}" class="actions" style="padding:0 1rem 1rem">
        <select name="status" class="form-control" style="width:auto">
            <option value="">{{ __('messages.dash.all') }}</option>
            @foreach($statuses as $s)
                <option value="{{ $s->value }}" @selected(($filters['status'] ?? null) === $s->value)>
                    {{ $s->label() }}
                </option>
            @endforeach
        </select>

        <select name="location_id" class="form-control" style="width:auto">
            <option value="">{{ __('cal.location') }}</option>
            @foreach(\App\Models\CustomerLocation::orderBy('name')->get() as $loc)
                <option value="{{ $loc->id }}" @selected(($filters['location_id'] ?? null) == $loc->id)>{{ $loc->name }}</option>
            @endforeach
        </select>

        <button type="submit" class="btn btn-secondary btn-sm">{{ __('messages.cal.month') }}</button>
        <a href="{{ route('admin.complaints.index') }}" class="btn btn-secondary btn-sm">{{ __('messages.dash.show_all') }}</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>{{ __('messages.cal.title') }}</th>
                <th>{{ __('messages.cal.location') }}</th>
                <th>{{ __('messages.cal.staff') }}</th>
                <th>{{ __('messages.cal.details') }}</th>
                <th>{{ __('messages.dash.complaints') }}</th>
                <th>{{ __('messages.cal.start') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($complaints as $complaint)
            <tr>
                <td><strong>{{ $complaint->title }}</strong></td>
                <td>{{ $complaint->location?->name ?? '—' }}</td>
                <td>{{ $complaint->assignedEmployee?->name ?? '—' }}</td>
                <td style="max-width:260px;font-size:.8rem;color:#94a3b8">{{ Str::limit($complaint->description, 80) }}</td>
                <td>
                    <span class="badge {{ $complaint->status->badgeClass() }}">{{ $complaint->status->label() }}</span>
                    @if($complaint->hasPhotos())
                        <span class="badge badge-gray" style="font-size:.7rem">{{ count($complaint->photos) }} 📷</span>
                    @endif
                </td>
                <td style="font-size:.8rem">
                    {{ $complaint->created_at->format('d.m.Y') }}
                    @if($complaint->isOpen())
                        <span style="color:#64748b">({{ $complaint->ageInDays() }}d)</span>
                    @endif
                </td>
                <td>
                    <a href="{{ route('admin.complaints.show', $complaint) }}" class="btn btn-secondary btn-sm">{{ __('time_page.details') }}</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center;color:#94a3b8;padding:1.5rem">
                    {{ __('messages.dash.no_complaints') }}
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">{{ $complaints->links() }}</div>
</div>
@endsection
