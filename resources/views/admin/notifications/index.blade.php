@extends('admin.layouts.app')

@section('title', __('notification.title'))
@section('content')
    <div class="card-header">
        <h1>{{ __('notification.title') }}</h1>
        <div class="actions">
            <form action="{{ route('admin.notifications.read-all') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm">{{ __('notification.mark_all_read') }}</button>
            </form>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.notifications.index') }}" style="margin-bottom:.75rem;">
        <div style="display:flex;gap:.5rem;align-items:center;">
            <select name="type" style="width:auto;">
                <option value="">{{ __('notification.all_types') }}</option>
                @foreach($types as $type)
                    <option value="{{ $type->value }}" {{ request('type') === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">{{ __('notification.filter') }}</button>
        </div>
    </form>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>{{ __('notification.type') }}</th>
                    <th>{{ __('notification.column_title') }}</th>
                    <th>{{ __('notification.message') }}</th>
                    <th>{{ __('notification.date') }}</th>
                    <th>{{ __('notification.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($notifications as $notification)
                    <tr style="{{ $notification->is_read ? 'opacity:.65;' : '' }}">
                        <td><span class="badge {{ $notification->type->badgeClass() }}">{{ $notification->type->label() }}</span></td>
                        <td>
                            <a href="{{ route('admin.notifications.show', $notification) }}">{{ $notification->title }}</a>
                        </td>
                        <td style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $notification->message }}</td>
                        <td>{{ $notification->created_at->format('d.m.Y H:i') }}</td>
                        <td>
                            @if(! $notification->is_read)
                                <form action="{{ route('admin.notifications.read', $notification) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm">{{ __('notification.mark_read') }}</button>
                                </form>
                            @else
                                <span class="badge badge-gray">{{ __('notification.read') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">{{ __('notification.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>

        {{ $notifications->withQueryString()->links() }}
    </div>
@endsection