@extends('admin.layouts.app')

@section('title', 'Zwei-Faktor-Authentifizierung')
@section('content')
    <div class="card" style="max-width:420px;margin:2rem auto;">
        <h1 class="card-title">Zwei-Faktor-Code</h1>
        <p style="font-size:.9rem;color:#475569;margin:.5rem 0 1rem;">
            Gib den 6-stelligen Code aus deiner Authenticator-App oder einen Recovery-Code ein.
        </p>
        <form method="POST" action="{{ route('admin.2fa.challenge.post') }}">
            @csrf
            <div class="form-group">
                <label for="code">Code</label>
                <input type="text" id="code" name="code" required autocomplete="off" autofocus
                       value="{{ old('code') }}">
                @error('code')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Bestätigen</button>
        </form>
    </div>
@endsection