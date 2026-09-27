@extends('admin.layouts.app')

@section('title', __('two_factor.title_recovery'))
@section('content')
    <div class="card" style="max-width:560px;margin:2rem auto;">
        <h1 class="card-title">{{ __('two_factor.heading_recovery') }}</h1>
        <p style="font-size:.9rem;color:#475569;margin:.5rem 0 1rem;">
            {{ __('two_factor.recovery_help') }}
        </p>

        @if($codes)
            <pre style="background:#f8fafc;padding:1rem;border-radius:8px;font-size:1rem;line-height:2;">@foreach($codes as $code){{ $code }}
@endforeach</pre>
        @else
            <p style="font-size:.9rem;">{{ __('two_factor.recovery_empty') }}</p>
        @endif

        <div style="margin-top:1rem;">
            <a href="{{ route('admin.two-factor.setup') }}" class="btn btn-secondary">{{ __('two_factor.back') }}</a>
        </div>
    </div>
@endsection