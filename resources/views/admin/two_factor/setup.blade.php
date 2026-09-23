@extends('admin.layouts.app')

@section('title', 'Zwei-Faktor-Authentifizierung')
@section('content')
    <div class="card-header">
        <h1>Zwei-Faktor-Authentifizierung</h1>
    </div>

    @if(session('two_factor_recovery_codes'))
        <div class="alert alert-success">
            <strong>Recovery-Codes gespeichert!</strong> Diese Codes werden nur einmal angezeigt — speichere sie sicher ab.
        </div>
        <div class="card">
            <pre style="font-size:1rem;line-height:1.8;">@foreach(session('two_factor_recovery_codes') as $code){{ $code }}
@endforeach</pre>
        </div>
        @php session()->forget('two_factor_recovery_codes'); @endphp
    @endif

    <div class="card">
        <h2 class="card-title">QR-Code scannen</h2>
        <p style="font-size:.9rem;color:#475569;margin:.5rem 0 1rem;">
            Scanne den QR-Code mit deiner Authenticator-App (Google Authenticator, 1Password, Authy …)
            und gib anschließend den 6-stelligen Code ein.
        </p>
        <div>{!! $qr_svg !!}</div>
        <p style="font-size:.85rem;margin-top:1rem;">
            Manuelle Eingabe: <code>{{ $secret }}</code>
        </p>
    </div>

    <div class="card">
        <h2 class="card-title">Code bestätigen</h2>
        <form method="POST" action="{{ route('admin.two-factor.confirm') }}">
            @csrf
            <div class="form-group">
                <label for="code">6-stelliger Code</label>
                <input type="text" id="code" name="code" maxlength="6" required autocomplete="off"
                       value="{{ old('code') }}" style="width:auto;">
                @error('code')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Aktivieren</button>
        </form>
    </div>

    <div class="card">
        <h2 class="card-title">Deaktivieren</h2>
        <p style="font-size:.9rem;color:#475569;margin-bottom:.75rem;">
            Zum Deaktivieren ist dein aktuelles Passwort erforderlich. Alle Recovery-Codes werden ungültig.
        </p>
        <form method="POST" action="{{ route('admin.two-factor.disable') }}">
            @csrf
            <div class="form-group">
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password" required style="width:auto;">
                @error('password')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-danger">Deaktivieren</button>
        </form>
    </div>
@endsection