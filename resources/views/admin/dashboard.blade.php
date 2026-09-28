@extends('admin.layouts.app')

@section('title', __('messages.dashboard'))
@section('content')
    @php
        $activeCustomers = \App\Models\Customer::active()->count();
        $employeeRoles   = [\App\Enums\RoleEnum::Vorarbeiter, \App\Enums\RoleEnum::Mitarbeiter];
        $activeEmployees = \App\Models\User::where('is_active', true)->whereIn('role', $employeeRoles)->count();
        $activeFixObjects = \App\Models\FixObject::where('is_active', true)->count();

        $monthHours = \App\Models\MonthlyReport::where('year', now()->year)
            ->where('month', now()->month)
            ->sum('total_paid_hours');

        $todayEvents = collect();
        try {
            $todayEvents = app(\App\Services\CalendarEngineService::class)
                ->forDay(\Carbon\CarbonImmutable::today(), ['fix', 'extra', 'shifts', 'internal', 'personal'])
                ->sortBy(fn($e) => $e->startAt->timestamp)
                ->take(8);
        } catch (\Throwable $e) {
            $todayEvents = collect();
        }

        $recentNotifications = \App\Models\AdminNotification::latest()->limit(5)->get();
        $unreadNotifications = \App\Models\AdminNotification::unread()->count()
                             + \App\Models\UserNotification::forUser((int) Auth::id())->unread()->count();
        $kpis = [
            ['label' => __('messages.dash.kpi_customers'),    'value' => $activeCustomers,    'ico' => '👥', 'accent' => '#3B82F6', 'hint' => __('messages.dash.kpi_customers_hint')],
            ['label' => __('messages.dash.kpi_employees'),    'value' => $activeEmployees,    'ico' => '🧑‍🔧', 'accent' => '#10B981', 'hint' => __('messages.dash.kpi_employees_hint')],
            ['label' => __('messages.dash.kpi_fix'),          'value' => $activeFixObjects,   'ico' => '🏢', 'accent' => '#D97706', 'hint' => __('messages.dash.kpi_fix_hint')],
            ['label' => __('messages.dash.kpi_hours'),        'value' => number_format((float) $monthHours, 1, ',', '.'), 'ico' => '🕒', 'accent' => '#D4AF37', 'hint' => __('messages.dash.kpi_hours_hint'), 'unit' => 'h'],
        ];
    @endphp

    {{-- ── Welcome hero ─────────────────────────────────────────────── --}}
    <div class="dash-hero">
        <div class="dash-hero-in">
            <div>
                <h2>{{ rtrim(__('messages.welcome'), '.,!?') }}{{ app()->getLocale() === 'ar' ? '، ' : ', ' }}{{ Auth::user()->name }} 👋</h2>
                <p>{{ __('messages.dashboard') }} · {{ now()->isoFormat('dddd, DD.MM.YYYY') }} — {{ __('messages.dash.mode_active') }}</p>
            </div>
            <div class="dash-hero-actions">
                <a href="{{ route('admin.calendar.index') }}" class="btn-gold-inline">📅 {{ __('messages.dash.to_calendar') }}</a>
                <span class="badge badge-gold">{{ Auth::user()->role->label() }}</span>
            </div>
        </div>
    </div>

    {{-- ── KPI row ──────────────────────────────────────────────────── --}}
    <div class="kpi-grid">
        @foreach($kpis as $kpi)
            <div class="kpi-card" style="--accent: {{ $kpi['accent'] }}">
                <div class="kpi-ico">{{ $kpi['ico'] }}</div>
                <div class="kpi-body">
                    <div class="kpi-value">
                        {{ $kpi['value'] }}<span class="kpi-unit">{{ $kpi['unit'] ?? '' }}</span>
                    </div>
                    <div class="kpi-label">{{ $kpi['label'] }}</div>
                    <div class="kpi-hint">{{ $kpi['hint'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ── Two-column: Today's schedule + Monitoring ───────────────── --}}
    <div class="dash-cols">
        <div class="card" style="flex: 1.4;">
            <div class="card-header">
                <span class="card-title">{{ __('messages.dash.todays') }} <span class="mini-pill">{{ __('messages.dash.pill_calendar') }}</span></span>
                <a href="{{ route('admin.calendar.index') }}" class="btn btn-secondary btn-sm">{{ __('messages.dash.show_all') }}</a>
            </div>
            @if($todayEvents->isEmpty())
                <div class="empty-state">
                    <div class="empty-ico">📅</div>
                    <p>{{ __('messages.dash.no_today') }}</p>
                </div>
            @else
                <div class="dash-timeline">
                    @foreach($todayEvents as $event)
                        <div class="tl-item">
                            <span class="tl-time">{{ $event->startAt->format('H:i') }}–{{ $event->endAt->format('H:i') }}</span>
                            <span class="tl-dot" style="background: {{ $event->color }}; box-shadow: 0 0 10px {{ $event->color }};"></span>
                            <div class="tl-body">
                                <div class="tl-title">
                                    {{ $event->title }}
                                    <span class="badge {{ $event->layer === 'jobs' ? 'badge-blue' : 'badge-purple' }}" style="margin-inline-start:.3rem;font-size:.62rem">
                                        {{ __('messages.cal.types.' . $event->type->value) }}
                                    </span>
                                </div>
                                <div class="tl-loc">{{ $event->location ?: '—' }}</div>
                            </div>
                            <span class="tl-status" style="color: {{ $event->color }}">{{ $event->statusLabel }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card" style="flex: 1;">
            <div class="card-header">
                <span class="card-title">⚠️ {{ __('messages.dash.complaints') }} <span class="mini-pill danger">{{ $unreadNotifications }}</span></span>
                <a href="{{ route('admin.notifications.index') }}" class="btn btn-secondary btn-sm">{{ __('messages.dash.all') }}</a>
            </div>
            @if($recentNotifications->isEmpty())
                <div class="empty-state">
                    <div class="empty-ico">🎉</div>
                    <p>{{ __('messages.dash.no_complaints') }}</p>
                </div>
            @else
                <div class="notif-list">
                    @foreach($recentNotifications as $n)
                        <a class="notif-item {{ $n->is_read ? '' : 'unread' }}" href="{{ route('admin.notifications.show', $n) }}">
                            <span class="notif-dot {{ $n->is_read ? 'off' : 'on' }}"></span>
                            <div>
                                <div class="notif-title">{{ $n->title }}</div>
                                <div class="notif-meta">{{ \Illuminate\Support\Str::limit($n->message ?? '', 70) }}</div>
                                <div class="notif-meta faint">{{ $n->created_at->diffForHumans() }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <style>
        .dash-hero {
            position: relative; overflow: hidden;
            background: linear-gradient(135deg, rgba(212,175,55,.14), rgba(22,25,32,.9) 45%, #13161C);
            border: 1px solid var(--border-gold);
            border-radius: 18px; padding: 1.5rem 1.75rem; margin-bottom: 1.35rem;
            box-shadow: 0 10px 40px rgba(0,0,0,.4), inset 0 1px 0 rgba(255,255,255,.04);
        }
        .dash-hero::after {
            content: ""; position: absolute; inset-inline-end: -60px; top: -60px;
            width: 240px; height: 240px; border-radius: 50%;
            background: radial-gradient(circle, rgba(212,175,55,.18), transparent 65%);
            pointer-events: none;
        }
        .dash-hero-in { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; position: relative; z-index: 1; }
        .dash-hero h2 { font-size: 1.35rem; font-weight: 800; color: var(--text); }
        .dash-hero p { font-size: .85rem; color: var(--text-muted); margin-top: .3rem; letter-spacing: .02em; }
        .dash-hero-actions { display: flex; align-items: center; gap: .7rem; }

        .btn-gold-inline {
            padding: .55rem 1.05rem; border-radius: 10px;
            background: linear-gradient(180deg, var(--gold-light), var(--gold) 55%, #B08A26);
            color: #141008; font-weight: 700; font-size: .82rem;
            box-shadow: 0 6px 20px rgba(212,175,55,.3);
        }
        .btn-gold-inline:hover { filter: brightness(1.08); transform: translateY(-1px); }

        .badge-gold {
            background: rgba(212,175,55,.14); color: var(--gold-bright); border: 1px solid rgba(212,175,55,.4);
            padding: .35rem .85rem; font-size: .78rem; font-weight: 700; border-radius: 999px;
        }

        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.35rem; }
        @media (max-width: 900px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }

        .kpi-card {
            display: flex; align-items: center; gap: .9rem;
            background: linear-gradient(180deg, var(--panel), #13161C);
            border: 1px solid var(--border); border-top: 2px solid var(--accent);
            border-radius: 16px; padding: 1.15rem 1.2rem;
            box-shadow: 0 8px 26px rgba(0,0,0,.35);
            position: relative; overflow: hidden;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 14px 34px rgba(0,0,0,.45); }
        .kpi-card::before {
            content: ""; position: absolute; inset-inline-end: -30px; top: -30px;
            width: 110px; height: 110px; border-radius: 50%;
            background: radial-gradient(circle, color-mix(in srgb, var(--accent) 18%, transparent), transparent 70%);
        }
        .kpi-ico {
            font-size: 1.6rem; width: 52px; height: 52px; flex: 0 0 52px;
            background: rgba(212,175,55,.1); border: 1px solid rgba(212,175,55,.25);
            border-radius: 14px; display: flex; align-items: center; justify-content: center;
        }
        .kpi-value { font-size: 1.7rem; font-weight: 800; color: var(--accent); line-height: 1; text-shadow: 0 0 22px color-mix(in srgb, var(--accent) 45%, transparent); }
        .kpi-unit { font-size: .85rem; font-weight: 600; margin-inline-start: .2rem; color: var(--text-muted); }
        .kpi-label { font-size: .8rem; font-weight: 700; color: var(--text); margin-top: .35rem; letter-spacing: .03em; }
        .kpi-hint { font-size: .7rem; color: var(--text-faint); margin-top: .1rem; }

        .dash-cols { display: flex; gap: 1.25rem; align-items: stretch; }
        @media (max-width: 980px) { .dash-cols { flex-direction: column; } }

        .mini-pill {
            display: inline-block; font-size: .62rem; font-weight: 700; letter-spacing: .06em;
            padding: .1rem .45rem; border-radius: 999px; vertical-align: middle;
            background: rgba(59,130,246,.15); color: #93C5FD; border: 1px solid rgba(59,130,246,.3);
            text-transform: uppercase;
        }
        .mini-pill.danger { background: rgba(239,68,68,.15); color: #FCA5A5; border-color: rgba(239,68,68,.35); }

        .empty-state {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: .6rem; padding: 2.4rem 1rem; color: var(--text-faint); text-align: center;
        }
        .empty-ico { font-size: 2rem; opacity: .6; }

        .dash-timeline { display: flex; flex-direction: column; }
        .tl-item {
            display: flex; align-items: center; gap: .85rem;
            padding: .7rem .2rem; border-bottom: 1px dashed rgba(42,46,57,.55);
        }
        .tl-item:last-child { border-bottom: none; }
        .tl-time { font-size: .72rem; color: var(--text-faint); white-space: nowrap; min-width: 76px; font-variant-numeric: tabular-nums; }
        .tl-dot { width: 10px; height: 10px; border-radius: 50%; flex: 0 0 10px; }
        .tl-body { flex: 1; min-width: 0; }
        .tl-title { font-size: .86rem; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tl-loc { font-size: .74rem; color: var(--text-muted); margin-top: .1rem; }
        .tl-status { font-size: .72rem; font-weight: 600; white-space: nowrap; }

        .notif-list { display: flex; flex-direction: column; }
        .notif-item {
            display: flex; gap: .7rem; padding: .62rem .2rem;
            border-bottom: 1px dashed rgba(42,46,57,.55); text-decoration: none;
            border-radius: 8px;
        }
        .notif-item:hover { background: rgba(212,175,55,.05); }
        .notif-item:last-child { border-bottom: none; }
        .notif-item.unread { border-inline-start: 3px solid var(--gold); padding-inline-start: .5rem; }
        .notif-dot { width: 8px; height: 8px; border-radius: 50%; margin-top: .45rem; flex: 0 0 8px; }
        .notif-dot.on { background: var(--gold-bright); box-shadow: 0 0 8px rgba(255,215,0,.6); }
        .notif-dot.off { background: #374151; }
        .notif-title { font-size: .84rem; font-weight: 600; color: var(--text); }
        .notif-meta { font-size: .72rem; color: var(--text-muted); margin-top: .12rem; }
        .notif-meta.faint { color: var(--text-faint); font-size: .68rem; }
        a { text-decoration: none; }
    </style>
@endsection