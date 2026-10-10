<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.login') }} — EMS Reinigungsservice</title>
    <style>
        :root {
            --bg: #0A0B0D;
            --panel: #161920;
            --panel-2: #1C1F26;
            --border: #2A2E39;
            --gold: #D4AF37;
            --gold-light: #E6C65B;
            --gold-bright: #FFD700;
            --gold-deep: #8A6D1F;
            --text: #F3F4F6;
            --text-muted: #9CA3AF;
            --red: #EF4444;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background:
                radial-gradient(900px 500px at 15% 0%, rgba(212,175,55,.10), transparent 55%),
                radial-gradient(1100px 700px at 95% 100%, rgba(212,175,55,.07), transparent 60%),
                radial-gradient(700px 700px at 50% 50%, rgba(28,31,38,.55), transparent 70%),
                var(--bg);
            background-attachment: fixed;
            color: var(--text);
        }
        /* subtle luxury grain */
        body::before {
            content: ""; position: fixed; inset: 0; pointer-events: none; opacity: .5;
            background-image: radial-gradient(rgba(255,255,255,.025) 1px, transparent 1px);
            background-size: 26px 26px;
        }
        .login-shell { position: relative; width: 100%; max-width: 440px; }

        .lang-switch {
            position: absolute; top: .9rem; inset-inline-end: .9rem; z-index: 2;
            display: inline-flex; align-items: center; gap: .45rem;
            background: rgba(28,31,38,.85); border: 1px solid rgba(212,175,55,.45);
            color: var(--gold-light); font-size: .76rem; font-weight: 800; letter-spacing: .03em;
            border-radius: 999px; padding: .4rem .8rem; text-decoration: none;
            box-shadow: 0 6px 18px rgba(0,0,0,.4);
            transition: all .15s ease;
        }
        .lang-switch:hover { border-color: var(--gold); transform: translateY(-1px); }
        .lang-switch svg { width: 14px; height: 14px; }

        .login-card {
            background: linear-gradient(180deg, #1a1d24 0%, #12141A 55%, #0E1013 100%);
            border: 1px solid var(--border);
            border-top: 1px solid rgba(212,175,55,.28);
            border-radius: 22px;
            box-shadow: 0 30px 80px rgba(0,0,0,.65), inset 0 1px 0 rgba(255,255,255,.04);
            padding: 2.6rem 2.4rem 2.4rem;
            position: relative;
            overflow: hidden;
        }
        .login-card::before {
            content: ""; position: absolute; top: 0; inset-inline: 0; height: 3px;
            background: linear-gradient(90deg, transparent, var(--gold-light), var(--gold), transparent);
        }
        .login-card::after {
            content: ""; position: absolute; top: -120px; inset-inline-end: -120px;
            width: 300px; height: 300px; border-radius: 50%;
            background: radial-gradient(circle, rgba(212,175,55,.10), transparent 65%);
            pointer-events: none;
        }

        .logo { text-align: center; margin-bottom: 1.9rem; position: relative; z-index: 1; }
        .logo img {
            width: 108px; height: 108px; border-radius: 50%;
            box-shadow: 0 0 34px rgba(212,175,55,.4), 0 0 2px rgba(212,175,55,.6);
            margin-bottom: .9rem;
        }
        .logo h1 {
            font-size: 1.5rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase;
            color: var(--gold-light);
            text-shadow: 0 0 22px rgba(212,175,55,.35);
        }
        .logo p {
            font-size: .82rem; color: var(--text-muted); margin-top: .3rem;
            letter-spacing: .18em; text-transform: uppercase;
        }

        .alert-error {
            background: rgba(239,68,68,.10); border: 1px solid rgba(239,68,68,.4);
            border-radius: 12px; padding: .7rem 1rem; margin-bottom: 1.15rem;
            color: #FCA5A5; font-size: .84rem;
        }

        form { position: relative; z-index: 1; }
        .form-group { margin-bottom: 1.15rem; }
        label { display: block; font-size: .8rem; font-weight: 600; color: var(--text-muted); margin-bottom: .4rem; letter-spacing: .03em; }

        .input-wrap { position: relative; }
        .input-wrap input {
            width: 100%; padding: .72rem 1rem .72rem 2.55rem;
            background: rgba(10,11,13,.72);
            border: 1px solid var(--border); border-radius: 12px;
            color: var(--text); font-size: .92rem; outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        .input-wrap .ic {
            position: absolute; inset-inline-start: .85rem; top: 50%; transform: translateY(-50%);
            color: var(--text-faint, #6B7280); display: flex;
        }
        .input-wrap .ic svg { width: 17px; height: 17px; }
        .input-wrap input:focus {
            border-color: rgba(212,175,55,.7);
            box-shadow: 0 0 0 3px rgba(212,175,55,.14), 0 0 22px rgba(212,175,55,.12);
        }
        .input-wrap input::placeholder { color: var(--text-faint, #6B7280); }
        .pw-toggle {
            position: absolute; inset-inline-end: .7rem; top: 50%; transform: translateY(-50%);
            background: transparent; border: none; cursor: pointer;
            color: var(--text-faint, #6B7280); display: flex; align-items: center; justify-content: center;
            padding: .25rem; border-radius: 8px;
            transition: color .15s ease;
        }
        .pw-toggle:hover { color: var(--gold-light); }
        .pw-toggle svg { width: 18px; height: 18px; }
        .input-wrap.has-toggle input { padding-inline-end: 2.6rem; }
        .error { color: #F87171; font-size: .78rem; margin-top: .3rem; }

        .remember {
            display: flex; align-items: center; gap: .55rem;
            font-size: .84rem; color: var(--text-muted); margin-bottom: 1.25rem; cursor: pointer;
        }
        .remember input { accent-color: var(--gold); }

        .btn-gold {
            width: 100%; padding: .8rem; cursor: pointer;
            background: linear-gradient(180deg, var(--gold-light), var(--gold) 55%, #B08A26);
            color: #141008; border: none; border-radius: 12px;
            font-size: .95rem; font-weight: 800; letter-spacing: .06em;
            box-shadow: 0 8px 26px rgba(212,175,55,.35), inset 0 1px 0 rgba(255,255,255,.35);
            transition: all .18s ease;
            display: flex; align-items: center; justify-content: center; gap: .5rem;
        }
        .btn-gold svg { width: 17px; height: 17px; }
        .btn-gold:hover {
            filter: brightness(1.08); transform: translateY(-1px);
            box-shadow: 0 12px 34px rgba(212,175,55,.45);
        }
        .btn-gold:active { transform: translateY(0); }

        .login-foot {
            margin-top: 1.6rem; text-align: center; position: relative; z-index: 1;
        }
        .login-foot .emblem { display: block; margin: 0 auto 1rem; }
        .emblem {
            display: inline-flex; align-items: center; gap: .5rem;
            color: rgba(212,175,55,.55); font-size: .72rem; letter-spacing: .24em;
            text-transform: uppercase;
        }
        .emblem::before, .emblem::after { content: ""; width: 34px; height: 1px; background: linear-gradient(90deg, transparent, rgba(212,175,55,.45)); }
        .emblem::after { background: linear-gradient(90deg, rgba(212,175,55,.45), transparent); }
    </style>
</head>
<body>
    <div class="login-shell">
        <div class="login-card">
            @php
                $activeLocale = app()->getLocale();
                $switchLocale = $activeLocale === 'ar' ? 'de' : 'ar';
                $switchLabel = $switchLocale === 'ar' ? 'العربية' : 'Deutsch';
            @endphp
            <a href="{{ route('locale.switch', $switchLocale) }}" class="lang-switch" title="{{ __('messages.topbar.switch_language') }} — {{ $switchLabel }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path><path d="M2 12h20"></path></svg>
                {{ strtoupper($activeLocale) }}
            </a>
            <div class="logo">
                <img src="{{ asset('images/logo-gold.png') }}" alt="{{ 'EMS Reinigungsservice' }}">
                <h1>EMS Reinigungsservice</h1>
                <p>{{ __('messages.login') }}</p>
            </div>

            @if ($errors->any())
                <div class="alert-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.post') }}">
                @csrf

                <div class="form-group">
                    <label for="email">{{ __('messages.email') }}</label>
                    <div class="input-wrap">
                        <span class="ic">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"></path><path d="m22 6-10 7L2 6"></path></svg>
                        </span>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            autocomplete="email"
                            autofocus
                            required
                            placeholder="admin@ems-reinigung.de"
                        >
                    </div>
                    @error('email')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password">{{ __('messages.password') }}</label>
                    <div class="input-wrap has-toggle">
                        <span class="ic">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        </span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                            placeholder="••••••••"
                        >
                        <button type="button" class="pw-toggle" id="pwToggle" aria-label="{{ __('messages.show_password') ?? 'Show password' }}" title="{{ __('messages.show_password') ?? 'Show password' }}">
                            <svg id="eyeOpen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg id="eyeClosed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><path d="M2 2l20 20"></path></svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>

                <label class="remember">
                    <input type="checkbox" id="remember" name="remember">
                    <span>{{ __('messages.remember_me') }}</span>
                </label>

                <button type="submit" class="btn-gold">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18 9 12l6-6"></path></svg>
                    {{ __('messages.login') }}
                </button>
            </form>

            <div class="login-foot">
                <span class="emblem">EMS · Reinigungsservice · 2026</span>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var toggle = document.getElementById('pwToggle');
            var input = document.getElementById('password');
            var eyeOpen = document.getElementById('eyeOpen');
            var eyeClosed = document.getElementById('eyeClosed');
            if (!toggle || !input) return;
            toggle.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                eyeOpen.style.display = show ? '' : 'none';
                eyeClosed.style.display = show ? 'none' : '';
            });
        })();
    </script>
</body>
</html>