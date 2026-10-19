<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'EMS') — EMS Reinigungsservice</title>
    <style>
        /* ═══════════════════════════════════════════════════════════════
           EMS Reinigungsservice — Dark Gold Luxury Admin Theme
           ═══════════════════════════════════════════════════════════════ */
        :root {
            --bg:            #0A0B0D;
            --bg-soft:       #0E1013;
            --panel:         #161920;
            --panel-2:       #1C1F26;
            --elev:          #22262F;
            --border:        #2A2E39;
            --border-gold:   #3B3322;
            --gold:          #D4AF37;
            --gold-light:    #E6C65B;
            --gold-bright:   #FFD700;
            --gold-deep:     #8A6D1F;
            --text:          #F3F4F6;
            --text-muted:    #9CA3AF;
            --text-faint:    #6B7280;
            --green:         #10B981;
            --red:           #EF4444;
            --blue:          #3B82F6;
            --sky:           #0284C7;
            --purple:        #8B5CF6;
            --orange:        #F59E0B;
            --amber:         #D97706;
            --sidebar-w:     264px;
            --topbar-h:      64px;
            --radius:        14px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body { height: 100%; }

        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background:
                radial-gradient(1200px 600px at 80% -10%, rgba(212,175,55,.06), transparent 60%),
                radial-gradient(900px 500px at -10% 110%, rgba(212,175,55,.04), transparent 55%),
                var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        a { color: var(--gold-light); text-decoration: none; }

        .app-shell { display: flex; min-height: 100vh; }

        /* ── Sidebar ──────────────────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-w);
            flex: 0 0 var(--sidebar-w);
            background: linear-gradient(180deg, #101217 0%, #0C0E12 70%, #0A0B0D 100%);
            border-inline-end: 1px solid var(--border-gold);
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
            z-index: 40;
        }

        .sidebar-brand {
            display: flex; align-items: center; gap: .7rem;
            padding: 1.1rem 1.1rem .9rem;
            border-bottom: 1px solid rgba(212,175,55,.12);
            background: linear-gradient(180deg, rgba(212,175,55,.08), transparent 80%);
        }
        .sidebar-brand img { width: 44px; height: 44px; border-radius: 50%; box-shadow: 0 0 18px rgba(212,175,55,.35); }
        .sidebar-brand .brand-name { font-size: .95rem; font-weight: 800; letter-spacing: .06em; color: var(--gold-light); line-height: 1.1; }
        .sidebar-brand .brand-name span { display: block; font-size: .62rem; font-weight: 600; letter-spacing: .22em; color: var(--text-muted); text-transform: uppercase; margin-top: .15rem; }

        .sidebar-scroll { flex: 1; overflow-y: auto; padding: .9rem .75rem 1.2rem; scrollbar-width: thin; scrollbar-color: var(--border) transparent; }

        .nav-section { margin-bottom: 1.15rem; }
        .nav-section-title {
            font-size: .62rem; font-weight: 700; letter-spacing: .16em; text-transform: uppercase;
            color: var(--text-faint); padding: 0 .6rem .35rem;
        }
        .nav-link {
            display: flex; align-items: center; gap: .65rem;
            padding: .52rem .65rem; margin-bottom: .15rem;
            border-radius: 10px; color: var(--text-muted);
            font-size: .875rem; font-weight: 500;
            border: 1px solid transparent;
            transition: all .15s ease; position: relative;
        }
        .nav-link .nav-ico { width: 18px; text-align: center; font-size: .95rem; opacity: .9; }
        .nav-link:hover { background: rgba(255,255,255,.04); color: var(--text); }
        .nav-link.active {
            background: linear-gradient(90deg, rgba(212,175,55,.16), rgba(212,175,55,.04));
            color: var(--gold-bright);
            border-color: rgba(212,175,55,.28);
            box-shadow: inset 2px 0 0 var(--gold), 0 2px 14px rgba(212,175,55,.08);
        }
        .nav-link .nav-badge {
            margin-inline-start: auto; min-width: 20px; text-align: center;
            background: var(--red); color: #fff; border-radius: 999px;
            font-size: .66rem; font-weight: 700; padding: .1rem .4rem;
            box-shadow: 0 0 10px rgba(239,68,68,.5);
        }

        /* Live status panel */
        .sidebar-live {
            border-top: 1px solid var(--border-gold);
            background: rgba(18,20,26,.9);
            padding: .8rem .9rem 1rem;
        }
        .live-title {
            display: flex; align-items: center; gap: .45rem;
            font-size: .66rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
            color: var(--gold-light); margin-bottom: .6rem;
        }
        .live-title .pulse { width: 8px; height: 8px; border-radius: 50%; background: var(--green); box-shadow: 0 0 0 0 rgba(16,185,129,.6); animation: pulse 2s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(16,185,129,.55); } 70% { box-shadow: 0 0 0 7px rgba(16,185,129,0); } 100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); } }
        .live-item {
            display: flex; align-items: center; gap: .5rem;
            padding: .32rem .2rem; font-size: .8rem; color: var(--text-muted);
            border-bottom: 1px dashed rgba(42,46,57,.55);
        }
        .live-item:last-child { border-bottom: none; }
        .live-item .dot { width: 9px; height: 9px; border-radius: 50%; flex: 0 0 9px; }
        .live-item .st-name { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .live-item .st-label { font-size: .66rem; color: var(--text-faint); }
        .dot-green  { background: var(--green); box-shadow: 0 0 8px rgba(16,185,129,.6); }
        .dot-blue   { background: var(--sky);   box-shadow: 0 0 8px rgba(2,132,199,.6); }
        .dot-yellow { background: #EAB308;     box-shadow: 0 0 8px rgba(234,179,8,.55); }
        .dot-red    { background: var(--red);   box-shadow: 0 0 8px rgba(239,68,68,.55); }
        .dot-gray   { background: #6B7280;      box-shadow: none; }
        .dot-white  { background: #E5E7EB;      box-shadow: 0 0 8px rgba(229,231,235,.4); }

        /* ── Top header ───────────────────────────────────────────── */
        .app-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .topbar {
            position: sticky; top: 0; z-index: 30;
            height: var(--topbar-h);
            display: flex; align-items: center; gap: 1rem;
            padding: 0 1.5rem;
            background: linear-gradient(90deg, rgba(22,25,32,.96), rgba(12,13,16,.96));
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-gold);
        }
        .topbar-page h1 { font-size: 1.05rem; font-weight: 700; letter-spacing: .02em; }
        .topbar-page .crumb { font-size: .68rem; color: var(--text-faint); margin-top: .05rem; letter-spacing: .04em; }

        .topbar-tools { margin-inline-start: auto; display: flex; align-items: center; gap: .7rem; }

        .header-search {
            display: flex; align-items: center; gap: .5rem;
            background: var(--panel); border: 1px solid var(--border);
            border-radius: 10px; padding: .42rem .7rem;
            min-width: 220px; color: var(--text-faint);
        }
        .header-search svg { width: 15px; height: 15px; flex: 0 0 15px; }
        .header-search input {
            background: transparent; border: none; outline: none; color: var(--text);
            font-size: .82rem; width: 100%;
        }
        .header-search input::placeholder { color: var(--text-faint); }
        .header-search:focus-within { border-color: rgba(212,175,55,.5); box-shadow: 0 0 0 3px rgba(212,175,55,.12); }

        .quick-actions { display: flex; gap: .45rem; }
        .btn-gold {
            display: inline-flex; align-items: center; gap: .4rem;
            background: linear-gradient(180deg, var(--gold-light), var(--gold) 55%, #B8962E);
            color: #141008; font-weight: 700; font-size: .78rem;
            border: none; border-radius: 9px; padding: .5rem .8rem;
            cursor: pointer; box-shadow: 0 4px 16px rgba(212,175,55,.28);
            transition: all .15s ease;
        }
        .btn-gold:hover { transform: translateY(-1px); box-shadow: 0 6px 22px rgba(212,175,55,.4); }
        .btn-gold-outline {
            display: inline-flex; align-items: center; gap: .4rem;
            background: transparent; color: var(--gold-light);
            border: 1px solid rgba(212,175,55,.45); font-weight: 600; font-size: .78rem;
            border-radius: 9px; padding: .5rem .8rem; cursor: pointer;
            transition: all .15s ease;
        }
        .btn-gold-outline:hover { background: rgba(212,175,55,.1); box-shadow: 0 0 14px rgba(212,175,55,.2); }

        .icon-btn {
            position: relative;
            width: 40px; height: 40px; border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            background: var(--panel); border: 1px solid var(--border);
            color: var(--text-muted); cursor: pointer; transition: all .15s ease;
        }
        .icon-btn:hover { color: var(--gold-light); border-color: rgba(212,175,55,.4); }
        .icon-btn svg { width: 18px; height: 18px; }
        .lang-btn { font-size: .78rem; font-weight: 800; letter-spacing: .03em; color: var(--gold-light); text-decoration: none; }
        .lang-btn:hover { color: var(--gold-bright); }
        .icon-btn .count-badge {
            position: absolute; top: -4px; inset-inline-end: -4px;
            min-width: 17px; height: 17px; padding: 0 4px; border-radius: 999px;
            background: var(--red); color: #fff; font-size: .62rem; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid var(--bg-soft); box-shadow: 0 0 10px rgba(239,68,68,.5);
        }

        .admin-chip {
            display: flex; align-items: center; gap: .6rem;
            padding-inline: .55rem .3rem;
            background: var(--panel); border: 1px solid var(--border);
            border-radius: 999px;
        }
        .admin-chip .avatar {
            width: 30px; height: 30px; border-radius: 50%;
            background: linear-gradient(135deg, var(--gold-light), var(--gold-deep));
            color: #141008; font-weight: 800; font-size: .78rem;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid rgba(212,175,55,.6);
        }
        .admin-chip .chip-name { font-size: .78rem; font-weight: 700; color: var(--text); line-height: 1.1; }
        .admin-chip .chip-role { font-size: .62rem; color: var(--gold-light); line-height: 1.1; letter-spacing: .05em; }

        /* ── Content area ─────────────────────────────────────────── */
        .main { flex: 1; padding: 1.5rem; max-width: 1400px; width: 100%; margin-inline: auto; }
        .main > .card:first-child { margin-top: 0; }

        .alert { padding: .75rem 1rem; border-radius: 11px; margin-bottom: 1rem; font-size: .875rem; border: 1px solid; }
        .alert-success { background: rgba(16,185,129,.1); color: #6EE7B7; border-color: rgba(16,185,129,.3); }
        .alert-error   { background: rgba(239,68,68,.1); color: #FCA5A5; border-color: rgba(239,68,68,.35); }

        .card {
            background: linear-gradient(180deg, var(--panel), #13161C);
            border: 1px solid var(--border);
            border-top: 1px solid rgba(212,175,55,.22);
            border-radius: var(--radius);
            box-shadow: 0 8px 30px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.02);
            padding: 1.5rem; margin-bottom: 1.25rem;
        }
        .card-header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .card-title  { font-size: 1.05rem; font-weight: 700; color: var(--text); }
        .meta { font-size: .8rem; color: var(--text-muted); display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }

        h1 { font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem; letter-spacing: .01em; }
        h2 { font-size: 1.05rem; font-weight: 700; }

        /* ── Tables ───────────────────────────────────────────────── */
        table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        thead tr { background: #15171E; }
        th {
            text-align: start; padding: .65rem .85rem; font-size: .72rem;
            letter-spacing: .06em; text-transform: uppercase; color: var(--text-muted);
            border-bottom: 1px solid var(--border-gold); font-weight: 700;
        }
        td { padding: .6rem .85rem; border-bottom: 1px solid rgba(42,46,57,.6); color: var(--text); }
        tbody tr:hover { background: rgba(212,175,55,.04); }
        td.num, th.num { text-align: end; font-variant-numeric: tabular-nums; }
        .num { text-align: end; font-variant-numeric: tabular-nums; }

        /* ── Badges ───────────────────────────────────────────────── */
        .badge { display: inline-block; padding: .2rem .55rem; border-radius: 999px; font-size: .74rem; font-weight: 700; border: 1px solid transparent; }
        .badge-green  { background: rgba(16,185,129,.14);  color: #6EE7B7; border-color: rgba(16,185,129,.35); }
        .badge-red    { background: rgba(239,68,68,.14);    color: #FCA5A5; border-color: rgba(239,68,68,.35); }
        .badge-gray   { background: rgba(107,114,128,.16);  color: #B6BDC7; border-color: rgba(107,114,128,.35); }
        .badge-blue   { background: rgba(59,130,246,.14);   color: #93C5FD; border-color: rgba(59,130,246,.35); }
        .badge-yellow { background: rgba(245,158,11,.14);   color: #FCD34D; border-color: rgba(245,158,11,.35); }
        .badge-purple { background: rgba(139,92,246,.14);   color: #C4B5FD; border-color: rgba(139,92,246,.35); }

        /* ── Buttons ──────────────────────────────────────────────── */
        .btn {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .5rem .95rem; border-radius: 9px; font-size: .84rem;
            font-weight: 600; cursor: pointer; text-decoration: none; border: 1px solid transparent;
            transition: all .15s ease; white-space: nowrap;
        }
        .btn-primary {
            background: linear-gradient(180deg, var(--gold-light), var(--gold) 55%, #B08A26);
            color: #141008 !important; border: none;
            box-shadow: 0 4px 16px rgba(212,175,55,.25);
        }
        .btn-primary:hover { filter: brightness(1.08); box-shadow: 0 6px 20px rgba(212,175,55,.4); transform: translateY(-1px); }
        .btn-secondary {
            background: var(--panel-2); color: var(--text); border: 1px solid var(--border);
        }
        .btn-secondary:hover { border-color: rgba(212,175,55,.45); color: var(--gold-light); background: #232732; }
        .btn-danger {
            background: rgba(239,68,68,.12); color: #FCA5A5; border: 1px solid rgba(239,68,68,.4);
        }
        .btn-danger:hover { background: rgba(239,68,68,.22); color: #FECACA; }
        .btn-sm { padding: .28rem .6rem; font-size: .78rem; border-radius: 8px; }

        /* ── Forms ────────────────────────────────────────────────── */
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: .8rem; font-weight: 600; color: var(--text-muted); margin-bottom: .35rem; letter-spacing: .02em; }
        input[type="text"], input[type="email"], input[type="password"],
        input[type="tel"], input[type="number"], input[type="date"],
        input[type="time"], select, textarea {
            width: 100%; padding: .55rem .8rem;
            background: var(--bg-soft);
            border: 1px solid var(--border); border-radius: 9px;
            color: var(--text); font-size: .875rem; outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        input:focus, select:focus, textarea:focus {
            border-color: rgba(212,175,55,.6);
            box-shadow: 0 0 0 3px rgba(212,175,55,.12);
        }
        input[type="checkbox"], input[type="radio"] { accent-color: var(--gold); width: auto; }
        .field-error { color: #F87171; font-size: .78rem; margin-top: .3rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 640px) { .form-row { grid-template-columns: 1fr; } }

        /* ── Misc ─────────────────────────────────────────────────── */
        .actions { display: flex; gap: .4rem; flex-wrap: wrap; align-items: center; }
        .color-dot { display: inline-block; width: 14px; height: 14px; border-radius: 50%; vertical-align: middle; margin-inline-end: 6px; border: 1px solid rgba(255,255,255,.25); box-shadow: 0 0 6px rgba(0,0,0,.4); }

        .pagination { display: flex; gap: .3rem; margin-top: 1rem; flex-wrap: wrap; }
        .pagination a, .pagination span, .pagination .disabled span,
        .pagination .active span {
            padding: .35rem .7rem; border-radius: 8px; font-size: .8rem;
            border: 1px solid var(--border); text-decoration: none; color: var(--text-muted);
            background: var(--panel-2);
        }
        .pagination .active span { background: linear-gradient(180deg, var(--gold), #B08A26); color: #141008; border-color: var(--gold); font-weight: 700; }
        .pagination a:hover { border-color: rgba(212,175,55,.5); color: var(--gold-light); }

        /* ── Notification dropdown ────────────────────────────────── */
        .dropdown { position: relative; }
        .dropdown-menu {
            position: absolute; inset-inline-end: 0; top: calc(100% + .5rem); z-index: 60;
            background: var(--panel-2); color: var(--text); border-radius: 14px;
            box-shadow: 0 20px 60px rgba(0,0,0,.6), inset 0 1px 0 rgba(255,255,255,.03);
            border: 1px solid var(--border-gold);
            min-width: 360px; max-width: 92vw;
            display: none; overflow: hidden;
        }
        .dropdown.open .dropdown-menu { display: block; }
        .dropdown-item {
            display: block; padding: .65rem .9rem; text-decoration: none;
            color: var(--text); border-bottom: 1px solid rgba(42,46,57,.6);
        }
        .dropdown-item:hover { background: rgba(255,255,255,.04); }
        .dropdown-item .dd-title { font-size: .85rem; font-weight: 600; color: var(--text); }
        .dropdown-item.unread { background: rgba(212,175,55,.07); border-inline-start: 3px solid var(--gold); }
        .dropdown-item .dd-meta { font-size: .72rem; color: var(--text-faint); margin-top: .15rem; }
        .dropdown-footer { padding: .6rem .9rem; text-align: center; border-top: 1px solid rgba(42,46,57,.6); }
        .dropdown-footer a { font-size: .8rem; color: var(--gold-light); font-weight: 600; }
        .dd-read-form { display: inline; }
        .dd-read-btn {
            float: inline-end; background: transparent; border: none; cursor: pointer;
            font-size: .72rem; color: var(--text-faint); text-decoration: underline;
        }

        /* ── Scrollbar ────────────────────────────────────────────── */
        ::-webkit-scrollbar { width: 9px; height: 9px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--elev); border-radius: 999px; border: 2px solid var(--bg); }
        ::-webkit-scrollbar-thumb:hover { background: var(--gold-deep); }

        /* ── RTL adjustments ──────────────────────────────────────── */
        html[dir="rtl"] .nav-link.active { box-shadow: inset -2px 0 0 var(--gold), 0 2px 14px rgba(212,175,55,.08); }
        html[dir="rtl"] .dropdown-item.unread { border-inline-start: 3px solid var(--gold); }
        html[dir="rtl"] .sidebar-brand .brand-name { letter-spacing: 0; }
        html[dir="rtl"] .nav-section-title,
        html[dir="rtl"] .live-title,
        html[dir="rtl"] .crumb,
        html[dir="rtl"] .admin-chip .chip-role { letter-spacing: 0; text-transform: none; }
        html[dir="rtl"] .kpi-label, html[dir="rtl"] .card-title { letter-spacing: 0; }

        /* ── Responsive ───────────────────────────────────────────── */
        @media (max-width: 1024px) {
            .sidebar { display: none; }
            .quick-actions .btn-gold { display: none; }
        }
        @media (max-width: 640px) {
            .header-search { display: none; }
            .admin-chip .chip-name, .admin-chip .chip-role { display: none; }
            .main { padding: 1rem; }
        }
    </style>
</head>
<body>
@php
    $adminNotifications = \App\Models\AdminNotification::latest()->limit(8)->get()
        ->map(fn($n) => (object) [
            'id'        => $n->id,
            'kind'      => 'admin',
            'title'     => $n->title,
            'message'   => $n->message,
            'created_at'=> $n->created_at,
            'unread'    => ! $n->is_read,
            'url'       => route('admin.notifications.show', $n),
        ]);
    $userNotifications = \App\Models\UserNotification::forUser((int) Auth::id())->latest()->limit(8)->get()
        ->map(fn($n) => (object) [
            'id'        => $n->id,
            'kind'      => 'user',
            'title'     => $n->title,
            'message'   => $n->message ?? '',
            'created_at'=> $n->created_at,
            'unread'    => ! $n->isRead(),
        ]);
    $notifications = $adminNotifications
        ->concat($userNotifications)
        ->sortByDesc('created_at')
        ->take(8)
        ->values();
    $unreadCount = \App\Models\AdminNotification::unread()->count()
                 + \App\Models\UserNotification::forUser((int) Auth::id())->unread()->count();
    $pendingAdjustments = \App\Models\TimeAdjustmentRequest::pending()->count();
    $openComplaints     = \App\Models\Complaint::open()->count();

    // ── Live employee status panel ────────────────────────────────
    // Priority order: paused beats everything, then actively working, then
    // travelling, then a finished day, then a scheduled-but-not-started day,
    // and finally nobody has any trace of them today.
    $employeeRoles = [\App\Enums\RoleEnum::Vorarbeiter, \App\Enums\RoleEnum::Mitarbeiter];
    $liveEmployees = \App\Models\User::query()
        ->where('is_active', true)
        ->whereIn('role', $employeeRoles)
        ->orderBy('name')
        ->get(['id', 'name', 'role']);

    $today   = now()->toDateString();
    $paused  = collect();
    $working = collect();
    $travelling = collect();
    $gpsToday = collect();

    try {
        $openExecutions = \App\Models\ExtraAuftragExecution::query()
            ->whereIn('status', [
                \App\Enums\ExtraExecutionStatusEnum::Travelling,
                \App\Enums\ExtraExecutionStatusEnum::Arrived,
                \App\Enums\ExtraExecutionStatusEnum::Working,
                \App\Enums\ExtraExecutionStatusEnum::PhotosAfter,
            ])
            ->get(['user_id', 'status', 'pause_started_at', 'pause_ended_at']);

        foreach ($openExecutions as $execution) {
            $id = (int) $execution->user_id;

            if ($execution->pause_started_at !== null && $execution->pause_ended_at === null) {
                $paused = $paused->push($id);
                continue;
            }

            if ($execution->status === \App\Enums\ExtraExecutionStatusEnum::Travelling) {
                $travelling = $travelling->push($id);
                continue;
            }

            $working = $working->push($id);
        }

        $schedules = \App\Models\FixObjectSchedule::where('scheduled_date', $today)
            ->whereIn('status', [
                \App\Enums\ScheduleStatusEnum::Pending,
                \App\Enums\ScheduleStatusEnum::InProgress,
                \App\Enums\ScheduleStatusEnum::Completed,
            ])
            ->with([
                'scheduleAssignments:id,schedule_id,user_id',
                'fixObject.assignments:id,fix_object_id,user_id,assigned_from,assigned_until',
            ])
            ->get(['id', 'fix_object_id', 'scheduled_date', 'status']);

        // effectiveEmployeeIds() resolves the day-level reassignment override
        // and falls back to the contract assignment, which the raw override
        // rows alone would miss.
        $scheduleState = collect();
        foreach ($schedules as $schedule) {
            foreach ($schedule->effectiveEmployeeIds() as $userId) {
                $scheduleState->push([
                    'user_id' => (int) $userId,
                    'status'  => $schedule->status->value,
                ]);
            }
        }

        $gpsToday = \App\Models\GpsPoint::whereDate('recorded_at', $today)->pluck('user_id')
            ->map(fn($id) => (int) $id);
    } catch (\Throwable $e) {
        $scheduleState = collect();
        $gpsToday = collect();
    }

    $liveItems = [];
    foreach ($liveEmployees as $emp) {
        $id = (int) $emp->id;
        $day = $scheduleState->where('user_id', $id)->pluck('status');

        $state = match (true) {
            $paused->contains($id)                             => ['dot-yellow', 'paused'],
            $working->contains($id)                            => ['dot-green',  'working'],
            $travelling->contains($id)                         => ['dot-blue',   'onway'],
            $day->contains('in_progress')                      => ['dot-green',  'working'],
            $day->contains('completed') && ! $day->contains('pending') && ! $day->contains('in_progress')
                                                              => ['dot-white',  'off_duty'],
            $gpsToday->contains($id) && $day->isNotEmpty()     => ['dot-blue',   'onway'],
            $day->contains('pending')                          => ['dot-red',    'not_started'],
            default                                            => ['dot-gray',   'off_duty'],
        };

        $liveItems[] = ['name' => $emp->name, 'dot' => $state[0], 'code' => $state[1]];
    }
    $activeLocale = app()->getLocale();
    $switchLocale = $activeLocale === 'ar' ? 'de' : 'ar';
    $switchLabel = $switchLocale === 'ar' ? 'العربية' : 'Deutsch';
@endphp
<div class="app-shell">
    {{-- ═══ SIDEBAR ═══ --}}
    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="{{ asset('images/logo-gold.png') }}" alt="EMS Reinigungsservice">
            <div class="brand-name">EMS<span>Reinigungsservice</span></div>
        </div>

        <div class="sidebar-scroll">
            <div class="nav-section">
                <div class="nav-section-title">{{ __('messages.nav.overview') }}</div>
                <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <span class="nav-ico">📊</span> {{ __('messages.nav.dashboard') }}
                </a>
                <a href="{{ route('admin.calendar.index') }}" class="nav-link {{ request()->routeIs('admin.calendar*') && ! request()->routeIs('admin.calendar.shifts*') && ! request()->routeIs('admin.calendar.appointments*') ? 'active' : '' }}">
                    <span class="nav-ico">📅</span> {{ __('messages.nav.calendar') }}
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">{{ __('messages.nav.masters') }}</div>
                <a href="{{ route('admin.customers.index') }}" class="nav-link {{ request()->routeIs('admin.customers*') ? 'active' : '' }}">
                    <span class="nav-ico">👥</span> {{ __('messages.customers.title') }}
                </a>
                <a href="{{ route('admin.employees.index') }}" class="nav-link {{ request()->routeIs('admin.employees*') ? 'active' : '' }}">
                    <span class="nav-ico">🧑‍🔧</span> {{ __('messages.employees.title') }}
                </a>
                <a href="{{ route('admin.fix-objects.index') }}" class="nav-link {{ request()->routeIs('admin.fix-objects*') ? 'active' : '' }}">
                    <span class="nav-ico">🏢</span> {{ __('messages.nav.fix_objects') }}
                </a>
                <a href="{{ route('admin.extra-auftraege.index') }}" class="nav-link {{ request()->routeIs('admin.extra-auftraege*') ? 'active' : '' }}">
                    <span class="nav-ico">📋</span> {{ __('messages.nav.extra_orders') }}
                </a>
                <a href="{{ route('admin.companies.index') }}" class="nav-link {{ request()->routeIs('admin.companies*') ? 'active' : '' }}">
                    <span class="nav-ico">🏭</span> {{ __('company.company') }}
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">{{ __('messages.nav.operations') }}</div>
                <a href="{{ route('admin.calendar.shifts.index') }}" class="nav-link {{ request()->routeIs('admin.calendar.shifts*') ? 'active' : '' }}">
                    <span class="nav-ico">⏱️</span> {{ __('messages.nav.shifts') }}
                </a>
                <a href="{{ route('admin.time-tracking.overview') }}" class="nav-link {{ request()->routeIs('admin.time-tracking*') ? 'active' : '' }}">
                    <span class="nav-ico">🕒</span> {{ __('messages.nav.time_tracking') }}
                </a>
                <a href="{{ route('admin.time-adjustments.index') }}" class="nav-link {{ request()->routeIs('admin.time-adjustments*') ? 'active' : '' }}">
                    <span class="nav-ico">🔧</span> {{ __('messages.nav.time_corrections') }}
                    @if($pendingAdjustments > 0)<span class="nav-badge">{{ $pendingAdjustments }}</span>@endif
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">{{ __('messages.nav.documentation') }}</div>
                <a href="{{ route('admin.monthly-reports.index') }}" class="nav-link {{ request()->routeIs('admin.monthly-reports*') ? 'active' : '' }}">
                    <span class="nav-ico">📈</span> {{ __('messages.nav.monthly_reports') }}
                </a>
                <a href="{{ route('admin.complaints.index') }}" class="nav-link {{ request()->routeIs('admin.complaints*') ? 'active' : '' }}">
                    <span class="nav-ico">⚠️</span> {{ __('messages.nav.reklamations') }}
                    @if($openComplaints > 0)<span class="nav-badge">{{ $openComplaints }}</span>@endif
                </a>
                <a href="{{ route('admin.audit-log.index') }}" class="nav-link {{ request()->routeIs('admin.audit-log*') ? 'active' : '' }}">
                    <span class="nav-ico">🛡️</span> {{ __('messages.nav.audit') }}
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">{{ __('messages.nav.system') }}</div>
                <a href="{{ route('admin.two-factor.setup') }}" class="nav-link {{ request()->routeIs('admin.two-factor*') ? 'active' : '' }}">
                    <span class="nav-ico">🔐</span> {{ __('messages.nav.two_factor') }}
                </a>
            </div>
        </div>

        <div class="sidebar-live">
            <div class="live-title"><span class="pulse"></span> {{ __('messages.nav.live') }}</div>
            @forelse($liveItems as $item)
                <div class="live-item">
                    <span class="dot {{ $item['dot'] }}"></span>
                    <span class="st-name">{{ $item['name'] }}</span>
                    <span class="st-label">{{ __('messages.live.' . $item['code']) }}</span>
                </div>
            @empty
                <div class="live-item"><span>{{ __('messages.topbar.no_employees') }}</span></div>
            @endforelse
        </div>
    </aside>

    {{-- ═══ MAIN ═══ --}}
    <div class="app-main">
        <header class="topbar">
            <div class="topbar-page">
                <h1>@yield('title', __('messages.dashboard'))</h1>
                <div class="crumb">{{ __('messages.topbar.crumb') }}</div>
            </div>

            <div class="topbar-tools">
                <div class="quick-actions">
                    <a href="{{ route('admin.extra-auftraege.create') }}" class="btn-gold">{{ __('messages.topbar.new_order') }}</a>
                    <a href="{{ route('admin.fix-objects.create') }}" class="btn-gold-outline">{{ __('messages.topbar.new_fix') }}</a>
                    <a href="{{ route('admin.time-tracking.overview') }}" class="btn-gold-outline">{{ __('messages.topbar.record_time') }}</a>
                </div>

                <div class="header-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path></svg>
                    <input type="text" placeholder="{{ __('messages.topbar.search') }}" id="global-search">
                </div>

                <a href="{{ route('admin.locale.switch', $switchLocale) }}" class="icon-btn lang-btn"
                   title="{{ __('messages.topbar.switch_language') }} — {{ $switchLabel }}" aria-label="{{ __('messages.topbar.switch_language') }} — {{ $switchLabel }}">
                    {{ strtoupper($activeLocale) }}
                </a>

                <div class="dropdown" id="notification-dropdown">
                    <button type="button" class="icon-btn js-notif-toggle" title="{{ __('messages.topbar.notifications') }}" aria-label="{{ __('messages.topbar.notifications') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path></svg>
                        @if($unreadCount > 0)<span class="count-badge">{{ $unreadCount }}</span>@endif
                    </button>
                    <div class="dropdown-menu">
                        @forelse($notifications as $n)
                            <div class="dropdown-item {{ $n->unread ? 'unread' : '' }}">
                                <form method="POST"
                                      action="{{ $n->kind === 'admin'
                                          ? route('admin.notifications.read', $n->id)
                                          : route('admin.notifications.user-read', $n->id) }}"
                                      class="dd-read-form"
                                      {{ $n->unread ? '' : 'hidden' }}>
                                    @csrf
                                    <button type="submit" class="dd-read-btn">{{ __('messages.topbar.mark_read') }}</button>
                                </form>
                                @if($n->kind === 'admin')
                                    <a href="{{ $n->url }}">
                                        <span class="dd-title">{{ $n->title }}</span>
                                    </a>
                                @else
                                    <span class="dd-title">{{ $n->title }}</span>
                                @endif
                                <div class="dd-meta">{{ \Illuminate\Support\Str::limit($n->message ?? '', 90) }}</div>
                                <div class="dd-meta">{{ $n->created_at->diffForHumans() }}</div>
                            </div>
                        @empty
                            <div class="dropdown-item"><span class="dd-meta">{{ __('messages.topbar.no_notifications') }}</span></div>
                        @endforelse
                        <div class="dropdown-footer">
                            <a href="{{ route('admin.notifications.index') }}">{{ __('messages.topbar.all_notifications') }}</a>
                        </div>
                    </div>
                </div>

                <div class="admin-chip">
                    <div class="avatar">{{ mb_strtoupper(mb_substr(Auth::user()?->name ?? 'A', 0, 1)) }}</div>
                    <div>
                        <div class="chip-name">{{ Auth::user()?->name ?? '' }}</div>
                        <div class="chip-role">{{ __('messages.topbar.admin_short') }}</div>
                    </div>
                </div>

                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="icon-btn" title="{{ __('messages.logout') }}" aria-label="{{ __('messages.logout') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><path d="m16 17 5-5-5-5"></path><path d="M21 12H9"></path></svg>
                    </button>
                </form>
            </div>
        </header>

        <main class="main">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-error">{{ $errors->first() }}</div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script>
    (function () {
        // ── Notification dropdown ──────────────────────────────
        var dropdown = document.getElementById('notification-dropdown');
        if (dropdown) {
            var toggle = dropdown.querySelector('.js-notif-toggle');
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                dropdown.classList.toggle('open');
            });
            document.addEventListener('click', function (e) {
                if (!dropdown.contains(e.target)) { dropdown.classList.remove('open'); }
            });
        }

        // ── Global quick search (CTRL+K) ──────────────────────
        var searchInput = document.getElementById('global-search');
        if (searchInput) {
            document.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    searchInput.focus();
                }
            });
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var q = searchInput.value.trim().toLowerCase();
                    if (!q) { return; }
                    var blockMapping = {
                        'kunde': '{{ route('admin.customers.index') }}',
                        'customer': '{{ route('admin.customers.index') }}',
                        'mitarbeiter': '{{ route('admin.employees.index') }}',
                        'employee': '{{ route('admin.employees.index') }}',
                        'fixobjekt': '{{ route('admin.fix-objects.index') }}',
                        'fix': '{{ route('admin.fix-objects.index') }}',
                        'auftrag': '{{ route('admin.extra-auftraege.index') }}',
                        'kalender': '{{ route('admin.calendar.index') }}',
                        'bericht': '{{ route('admin.monthly-reports.index') }}',
                        'reklamation': '{{ route('admin.complaints.index') }}',
                        'complaint': '{{ route('admin.complaints.index') }}',
                        'zeit': '{{ route('admin.time-tracking.overview') }}'
                    };
                    for (var key in blockMapping) {
                        if (q.indexOf(key) !== -1) {
                            window.location.href = blockMapping[key];
                            return;
                        }
                    }
                    var links = document.querySelectorAll('.nav-link');
                    for (var i = 0; i < links.length; i++) {
                        if (links[i].textContent.toLowerCase().indexOf(q) !== -1) {
                            window.location.href = links[i].getAttribute('href');
                            return;
                        }
                    }
                }
            });
        }
    })();
</script>
</body>
</html>