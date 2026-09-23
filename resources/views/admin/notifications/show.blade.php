@extends('admin.layouts.app')

@section('title', $notification->title)
@section('content')
    <h1>
        <span class="badge {{ $notification->type->badgeClass() }}">{{ $notification->type->label() }}</span>
        {{ $notification->title }}
    </h1>

    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1rem;">
            <span style="font-size:.8rem;color:#64748b;">{{ $notification->created_at->format('d.m.Y H:i:s') }}</span>
            @if(! $notification->is_read)
                <form action="{{ route('admin.notifications.read', $notification) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">Als gelesen markieren</button>
                </form>
            @endif
        </div>

        <div style="white-space:pre-wrap;line-height:1.7;">{{ $notification->message }}</div>

        @if($notification->payload)
            <hr style="margin:1.25rem 0;border:none;border-top:1px solid #e2e8f0;">
            <details>
                <summary style="cursor:pointer;font-size:.85rem;font-weight:600;">Details (JSON)</summary>
                <pre style="background:#f8fafc;padding:1rem;border-radius:8px;overflow-x:auto;margin-top:.5rem;font-size:.8rem;">{{ json_encode($notification->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </details>
        @endif
    </div>

    <a href="{{ route('admin.notifications.index') }}" class="btn btn-secondary">← Zurück</a>
@endsection