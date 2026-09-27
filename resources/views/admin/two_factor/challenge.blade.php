@extends('admin.layouts.app')

@section('title', __('two_factor.title_challenge'))
@section('content')
    <div class="card" style="max-width:420px;margin:2rem auto;">
        <h1 class="card-title">{{ __('two_factor.heading_challenge') }}</h1>
        <p style="font-size:.9rem;color:#475569;margin:.5rem 0 1rem;">
            {{ __('two_factor.challenge_help') }}
        </p>
        <form method="POST" action="{{ route('admin.2fa.challenge.post') }}">
            @csrf
            <div class="form-group">
                <label for="code">{{ __('two_factor.label_code') }}</label>
                <input type="text" id="code" name="code" required autocomplete="off" autofocus
                       value="{{ old('code') }}">
                @error('code')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">{{ __('two_factor.submit_confirm') }}</button>
        </form>
    </div>
@endsection