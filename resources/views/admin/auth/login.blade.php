<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.login') }} — EMS Reinigungsservice</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
        }
        .logo { text-align: center; margin-bottom: 1.75rem; }
        .logo h1 { font-size: 1.4rem; font-weight: 700; color: #1e293b; }
        .logo p  { font-size: .85rem; color: #64748b; margin-top: .25rem; }
        .form-group { margin-bottom: 1.1rem; }
        label { display: block; font-size: .85rem; font-weight: 500; color: #374151; margin-bottom: .35rem; }
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: .6rem .85rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: .9rem;
            outline: none;
            transition: border-color .15s;
        }
        input:focus { border-color: #3b82f6; }
        .error { color: #dc2626; font-size: .8rem; margin-top: .3rem; }
        .alert-error {
            background: #fef2f2; border: 1px solid #fecaca;
            border-radius: 8px; padding: .75rem 1rem;
            color: #b91c1c; font-size: .85rem; margin-bottom: 1rem;
        }
        .remember { display: flex; align-items: center; gap: .5rem; font-size: .85rem; color: #374151; margin-bottom: 1.25rem; }
        .btn-primary {
            width: 100%; padding: .7rem;
            background: #1e40af; color: #fff;
            border: none; border-radius: 8px;
            font-size: .95rem; font-weight: 600;
            cursor: pointer; transition: background .15s;
        }
        .btn-primary:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <h1>EMS Reinigungsservice</h1>
            <p>{{ __('messages.login') }}</p>
        </div>

        {{-- Error summary --}}
        @if ($errors->any())
            <div class="alert-error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.post') }}">
            @csrf

            <div class="form-group">
                <label for="email">{{ __('messages.email') }}</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    autofocus
                    required
                >
                @error('email')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-group">
                <label for="password">{{ __('messages.password') }}</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
                @error('password')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>

            <div class="remember">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember" style="margin-bottom:0">{{ __('messages.remember_me') }}</label>
            </div>

            <button type="submit" class="btn-primary">
                {{ __('messages.login') }}
            </button>
        </form>
    </div>
</body>
</html>
