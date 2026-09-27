@extends('admin.layouts.app')

@section('title', __('two_factor.title_setup'))
@section('content')
    <div class="card-header">
        <h1>{{ __('two_factor.heading_setup') }}</h1>
    </div>

    @if(session('two_factor_recovery_codes'))
        <div class="alert alert-success">
            <strong>{{ __('two_factor.stored_alert') }}</strong> {{ __('two_factor.stored_help') }}
        </div>
        <div class="card">
            <pre style="font-size:1rem;line-height:1.8;">@foreach(session('two_factor_recovery_codes') as $code){{ $code }}
@endforeach</pre>
        </div>
        @php session()->forget('two_factor_recovery_codes'); @endphp
    @endif

    <div class="card">
        <h2 class="card-title">{{ __('two_factor.scan_heading') }}</h2>
        <p style="font-size:.9rem;color:#475569;margin:.5rem 0 1rem;">
            {{ __('two_factor.scan_help') }}
        </p>
        <div>{!! $qr_svg !!}</div>
        <p style="font-size:.85rem;margin-top:1rem;">
            {{ __('two_factor.manual_label') }} <code>{{ $secret }}</code>
        </p>
    </div>

    <div class="card">
        <h2 class="card-title">{{ __('two_factor.confirm_heading') }}</h2>
        <form method="POST" action="{{ route('admin.two-factor.confirm') }}">
            @csrf
            <div class="form-group">
                <label for="code">{{ __('two_factor.label_6digit') }}</label>
                <input type="text" id="code" name="code" maxlength="6" required autocomplete="off"
                       value="{{ old('code') }}" style="width:auto;">
                @error('code')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">{{ __('two_factor.activate') }}</button>
        </form>
    </div>

    <div class="card">
        <h2 class="card-title">{{ __('two_factor.disable_heading') }}</h2>
        <p style="font-size:.9rem;color:#475569;margin-bottom:.75rem;">
            {{ __('two_factor.disable_help') }}
        </p>
        <form method="POST" action="{{ route('admin.two-factor.disable') }}">
            @csrf
            <div class="form-group">
                <label for="password">{{ __('two_factor.label_password') }}</label>
                <input type="password" id="password" name="password" required style="width:auto;">
                @error('password')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-danger">{{ __('two_factor.disable_submit') }}</button>
        </form>
    </div>
@endsection