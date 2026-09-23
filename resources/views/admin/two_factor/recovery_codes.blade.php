@extends('admin.layouts.app')

@section('title', 'Recovery-Codes')
@section('content')
    <div class="card" style="max-width:560px;margin:2rem auto;">
        <h1 class="card-title">Recovery-Codes</h1>
        <p style="font-size:.9rem;color:#475569;margin:.5rem 0 1rem;">
            Bewahre diese Codes sicher auf. Jeder Code kann genau einmal verwendet werden.
        </p>

        @if($codes)
            <pre style="background:#f8fafc;padding:1rem;border-radius:8px;font-size:1rem;line-height:2;">@foreach($codes as $code){{ $code }}
@endforeach</pre>
        @else
            <p style="font-size:.9rem;">Keine neuen Recovery-Codes verfügbar.</p>
        @endif

        <div style="margin-top:1rem;">
            <a href="{{ route('admin.two-factor.setup') }}" class="btn btn-secondary">Zurück</a>
        </div>
    </div>
@endsection