<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'EMS') — EMS Reinigungsservice</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f1f5f9; color: #1e293b; }
        .navbar {
            background: #1e40af; color: #fff;
            display: flex; align-items: center; justify-content: space-between;
            padding: .75rem 1.5rem; gap: 1rem;
        }
        .navbar-brand { font-weight: 700; font-size: 1rem; color: #fff; text-decoration: none; }
        .navbar-nav { display: flex; gap: .5rem; align-items: center; }
        .nav-link {
            color: rgba(255,255,255,.85); text-decoration: none;
            padding: .3rem .7rem; border-radius: 6px; font-size: .85rem;
        }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,.15); color: #fff; }
        .logout-form { display: inline; }
        .btn-logout {
            background: transparent; border: 1px solid rgba(255,255,255,.4);
            color: #fff; padding: .3rem .7rem; border-radius: 6px;
            cursor: pointer; font-size: .85rem;
        }
        .main { max-width: 1200px; margin: 1.5rem auto; padding: 0 1rem; }
        .alert { padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .875rem; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .card { background: #fff; border-radius: 10px; box-shadow: 0 1px 8px rgba(0,0,0,.06); padding: 1.5rem; margin-bottom: 1.25rem; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .card-title  { font-size: 1.1rem; font-weight: 600; }
        h1 { font-size: 1.35rem; font-weight: 700; margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        thead tr { background: #f8fafc; }
        th, td { padding: .6rem .85rem; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .badge { display: inline-block; padding: .2rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 600; }
        .badge-green  { background: #dcfce7; color: #15803d; }
        .badge-red    { background: #fee2e2; color: #b91c1c; }
        .badge-gray   { background: #f1f5f9; color: #475569; }
        .badge-blue   { background: #dbeafe; color: #1d4ed8; }
        .badge-yellow { background: #fef9c3; color: #a16207; }
        .btn { display: inline-block; padding: .45rem .9rem; border-radius: 7px; font-size: .85rem; font-weight: 500; cursor: pointer; text-decoration: none; border: none; }
        .btn-primary { background: #1e40af; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: #f1f5f9; color: #1e293b; border: 1px solid #e2e8f0; }
        .btn-secondary:hover { background: #e2e8f0; }
        .btn-danger { background: #fee2e2; color: #b91c1c; }
        .btn-danger:hover { background: #fecaca; }
        .btn-sm { padding: .25rem .6rem; font-size: .8rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: .85rem; font-weight: 500; color: #374151; margin-bottom: .3rem; }
        input[type="text"], input[type="email"], input[type="password"],
        input[type="tel"], input[type="number"], input[type="date"],
        input[type="time"], select, textarea {
            width: 100%; padding: .55rem .8rem;
            border: 1px solid #d1d5db; border-radius: 7px;
            font-size: .875rem; outline: none;
        }
        input:focus, select:focus, textarea:focus { border-color: #3b82f6; }
        .field-error { color: #dc2626; font-size: .78rem; margin-top: .25rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .actions { display: flex; gap: .4rem; }
        .pagination { display: flex; gap: .3rem; margin-top: 1rem; }
        .pagination a, .pagination span {
            padding: .35rem .7rem; border-radius: 6px; font-size: .8rem;
            border: 1px solid #e2e8f0; text-decoration: none; color: #374151;
        }
        .pagination .active span { background: #1e40af; color: #fff; border-color: #1e40af; }
        .color-dot { display: inline-block; width: 14px; height: 14px; border-radius: 50%; vertical-align: middle; margin-right: 6px; border: 1px solid rgba(0,0,0,.1); }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="{{ route('admin.dashboard') }}" class="navbar-brand">EMS Reinigungsservice</a>
        <div class="navbar-nav">
            <a href="{{ route('admin.customers.index') }}" class="nav-link {{ request()->routeIs('admin.customers*') ? 'active' : '' }}">{{ __('messages.customers.title') }}</a>
            <a href="{{ route('admin.employees.index') }}" class="nav-link {{ request()->routeIs('admin.employees*') ? 'active' : '' }}">{{ __('messages.employees.title') }}</a>
            <a href="{{ route('admin.fix-objects.index') }}" class="nav-link {{ request()->routeIs('admin.fix-objects*') ? 'active' : '' }}">Fixobjekte</a>
            <a href="{{ route('admin.extra-auftraege.index') }}" class="nav-link {{ request()->routeIs('admin.extra-auftraege*') ? 'active' : '' }}">Extra-Aufträge</a>
            <a href="{{ route('admin.calendar.index') }}" class="nav-link {{ request()->routeIs('admin.calendar*') ? 'active' : '' }}">Kalender</a>
            <a href="{{ route('admin.notifications.index') }}" class="nav-link {{ request()->routeIs('admin.notifications*') ? 'active' : '' }}">
                Meldungen
                @php $unread = \App\Models\AdminNotification::unread()->count(); @endphp
                @if($unread > 0)<span class="badge badge-red" style="font-size:.7rem">{{ $unread }}</span>@endif
            </a>
            <a href="{{ route('admin.teamup.edit') }}" class="nav-link {{ request()->routeIs('admin.teamup*') ? 'active' : '' }}">Teamup</a>
            <a href="{{ route('admin.time-adjustments.index') }}" class="nav-link {{ request()->routeIs('admin.time-adjustments*') ? 'active' : '' }}">
                Zeitkorrekturen
                @php $pending = \App\Models\TimeAdjustmentRequest::pending()->count(); @endphp
                @if($pending > 0)<span class="badge badge-red" style="font-size:.7rem">{{ $pending }}</span>@endif
            </a>
            <span style="color:rgba(255,255,255,.5);font-size:.8rem">{{ Auth::user()->name }}</span>
            <form action="{{ route('admin.logout') }}" method="POST" class="logout-form">
                @csrf
                <button type="submit" class="btn-logout">{{ __('messages.logout') }}</button>
            </form>
        </div>
    </nav>

    <div class="main">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        @yield('content')
    </div>
</body>
</html>
