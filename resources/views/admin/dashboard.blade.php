<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.dashboard') }} — EMS Reinigungsservice</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f1f5f9; color: #1e293b; }
        .navbar {
            background: #1e40af; color: #fff;
            display: flex; align-items: center; justify-content: space-between;
            padding: .85rem 2rem;
        }
        .navbar h1 { font-size: 1.1rem; font-weight: 700; }
        .navbar span { font-size: .85rem; opacity: .85; }
        .logout-btn {
            background: transparent; border: 1px solid rgba(255,255,255,.5);
            color: #fff; padding: .35rem .85rem; border-radius: 6px;
            cursor: pointer; font-size: .85rem; margin-left: 1rem;
        }
        .logout-btn:hover { background: rgba(255,255,255,.1); }
        .container { max-width: 1100px; margin: 2rem auto; padding: 0 1.5rem; }
        .welcome-card {
            background: #fff; border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            padding: 2rem;
        }
        .welcome-card h2 { font-size: 1.25rem; margin-bottom: .5rem; }
        .badge {
            display: inline-block; background: #dbeafe; color: #1d4ed8;
            font-size: .75rem; font-weight: 600; padding: .2rem .6rem;
            border-radius: 999px; margin-left: .5rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>EMS Reinigungsservice</h1>
        <div style="display:flex;align-items:center">
            <span>{{ Auth::user()->name }}</span>
            <form method="POST" action="{{ route('admin.logout') }}" style="display:inline">
                @csrf
                <button type="submit" class="logout-btn">{{ __('messages.logout') }}</button>
            </form>
        </div>
    </nav>

    <div class="container">
        <div class="welcome-card">
            <h2>
                {{ __('messages.welcome') }}
                <span class="badge">{{ Auth::user()->role->label() }}</span>
            </h2>
            <p style="color:#64748b;margin-top:.5rem;font-size:.9rem">
                {{ __('messages.dashboard') }} — Phase 1 ✓
            </p>
        </div>
    </div>
</body>
</html>
