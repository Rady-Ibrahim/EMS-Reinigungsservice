@extends('admin.layouts.app')

@section('title', __('messages.dashboard'))
@section('content')
    @php
        $recentNotifications = \App\Models\AdminNotification::latest()->limit(5)->get();
        $unreadNotifications = \App\Models\AdminNotification::unread()->count()
                             + \App\Models\UserNotification::forUser((int) Auth::id())->unread()->count();
        $kpis = [
            ['label' => __('messages.dash.kpi_customers'), 'value' => $activeCustomers, 'ico' => '👥', 'accent' => '#3B82F6', 'hint' => __('messages.dash.kpi_customers_hint')],
            ['label' => __('messages.dash.kpi_employees'), 'value' => $activeEmployees, 'ico' => '🧑‍🔧', 'accent' => '#10B981', 'hint' => __('messages.dash.kpi_employees_hint')],
            ['label' => __('messages.dash.kpi_fix'), 'value' => $activeFixObjects, 'ico' => '🏢', 'accent' => '#D97706', 'hint' => __('messages.dash.kpi_fix_hint')],
            ['label' => __('messages.dash.kpi_hours'), 'value' => number_format((float) $monthHours, 1, ',', '.'), 'ico' => '🕒', 'accent' => '#D4AF37', 'hint' => __('messages.dash.kpi_hours_hint'), 'unit' => 'h'],
            ['label' => __('messages.dash.kpi_today_jobs'), 'value' => $todayJobCount, 'ico' => '🗓️', 'accent' => '#8B5CF6', 'hint' => __('messages.dash.kpi_today_jobs_hint')],
            ['label' => __('messages.dash.kpi_pending_adjustments'), 'value' => $pendingAdjustments, 'ico' => '⏱️', 'accent' => '#EF4444', 'hint' => __('messages.dash.kpi_pending_adjustments_hint')],
        ];
        if ($isAdministrator && $financialOverview !== null) {
            $kpis[] = ['label' => __('messages.dash.kpi_revenue'), 'value' => number_format($financialOverview['revenue'], 2, ',', '.'), 'ico' => '💶', 'accent' => '#10B981', 'hint' => __('messages.dash.admin_only'), 'unit' => '€'];
            $kpis[] = ['label' => __('messages.dash.kpi_profit'), 'value' => number_format($financialOverview['profit'], 2, ',', '.'), 'ico' => '📈', 'accent' => '#D4AF37', 'hint' => __('messages.dash.admin_only'), 'unit' => '€'];
        }
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

    {{-- ── Two-column: Today's operations + Monitoring ──────────────── --}}
    <div class="dash-cols">
        <div class="card" style="flex: 1.4;">
            <div class="card-header">
                <span class="card-title">{{ __('messages.dash.todays') }} <span class="mini-pill">{{ $todayJobCount }}</span></span>
                <a href="{{ route('admin.calendar.index') }}" class="btn btn-secondary btn-sm">{{ __('messages.dash.show_all') }}</a>
            </div>
            @if($todayJobs->isEmpty())
                <div class="empty-state">
                    <div class="empty-ico">📅</div>
                    <p>{{ __('messages.dash.no_today') }}</p>
                </div>
            @else
                <div class="operations-table-wrap">
                    <table class="operations-table">
                        <thead>
                            <tr>
                                <th>{{ __('messages.dash.job') }}</th>
                                <th>{{ __('messages.dash.team') }}</th>
                                <th>{{ __('messages.dash.documentation') }}</th>
                                <th>{{ __('messages.dash.security') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($todayJobs as $job)
                                <tr>
                                    <td>
                                        <a class="operation-title" href="{{ $job['url'] }}">{{ $job['title'] }}</a>
                                        <div class="operation-meta">{{ $job['customer'] ?: '—' }} · {{ $job['location_name'] ?: '—' }}</div>
                                        @if($job['address'])
                                            <div class="operation-meta">{{ $job['address'] }}</div>
                                        @endif
                                        @if($job['coordinates'])
                                            <div class="operation-meta">{{ __('messages.dash.coordinates') }}: {{ $job['coordinates'] }}</div>
                                        @endif
                                        <div class="operation-meta">
                                            {{ $job['start'] ? \Illuminate\Support\Carbon::parse($job['start'])->format('H:i') : __('messages.cal.all_day') }}
                                            @if($job['end']) – {{ \Illuminate\Support\Carbon::parse($job['end'])->format('H:i') }} @endif
                                            · {{ $job['status'] }}
                                        </div>
                                    </td>
                                    <td>
                                        @forelse($job['team'] as $member)
                                            <div class="operation-team">
                                                @if($member['is_leader']) <span class="leader-star" title="{{ __('messages.dash.supervisor') }}">★</span> @endif
                                                <span>{{ $member['name'] }}</span>
                                                @if($member['role'])
                                                    <span class="role-chip">{{ __('messages.roles.' . $member['role']->value) }}</span>
                                                @endif
                                            </div>
                                        @empty
                                            <span class="operation-meta">—</span>
                                        @endforelse
                                    </td>
                                    <td>
                                        <div class="operation-meta">📷 {{ $job['photo_count'] }} {{ __('messages.dash.photos') }}</div>
                                        <div class="operation-meta">
                                            ✓ {{ $job['checklist'] === null
                                                ? __('messages.dash.checklist_not_tracked')
                                                : $job['checklist']['done'].'/'.$job['checklist']['total'].' '.__('messages.dash.checklist_done') }}
                                        </div>
                                    </td>
                                    <td>
                                        @if($job['has_key_safe']) <span class="security-chip">🔑 {{ __('messages.dash.key_safe') }}</span> @endif
                                        @if($job['has_alarm_code']) <span class="security-chip">🔒 {{ __('messages.dash.alarm_configured') }}</span> @endif
                                        @if(!$job['has_key_safe'] && !$job['has_alarm_code']) <span class="operation-meta">—</span> @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
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

    @if($isAdministrator && $financialOverview !== null)
        <div class="card company-financial-card">
            <div class="card-header">
                <span class="card-title">🏢 {{ __('messages.dash.companies_financial') }}</span>
                <span class="mini-pill">{{ __('messages.dash.admin_only') }}</span>
            </div>
            <div class="company-financial-summary">
                <div>
                    <div class="financial-value">{{ number_format($financialOverview['company_payout'], 2, ',', '.') }} €</div>
                    <div class="operation-meta">{{ __('messages.dash.company_payout_month') }}</div>
                </div>
                <div>
                    <div class="financial-value">{{ $financialOverview['active_company_employees'] }}</div>
                    <div class="operation-meta">{{ __('messages.dash.active_company_employees') }}</div>
                </div>
                <div>
                    <div class="financial-value">{{ number_format($financialOverview['labor_cost'], 2, ',', '.') }} €</div>
                    <div class="operation-meta">{{ __('messages.dash.labor_cost_month') }}</div>
                </div>
            </div>
            @if($financialOverview['companies']->isNotEmpty())
                <div class="company-payout-list">
                    @foreach($financialOverview['companies'] as $company)
                        <div class="company-payout-item">
                            <strong>{{ $company['name'] }}</strong>
                            <span>{{ $company['active_employees'] }} {{ __('messages.dash.employees_short') }}</span>
                            <span>{{ number_format($company['payout'], 2, ',', '.') }} €</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

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

        .kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.35rem; }
        @media (max-width: 1200px) { .kpi-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 900px) { .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 560px) { .kpi-grid { grid-template-columns: 1fr; } }

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

        .operations-table-wrap { overflow-x: auto; }
        .operations-table { width: 100%; border-collapse: collapse; min-width: 720px; }
        .operations-table th, .operations-table td { padding: .75rem .65rem; text-align: start; vertical-align: top; border-bottom: 1px dashed rgba(42,46,57,.7); }
        .operations-table th { color: var(--text-faint); font-size: .67rem; text-transform: uppercase; letter-spacing: .06em; white-space: nowrap; }
        .operation-title { font-size: .85rem; font-weight: 700; color: var(--text); }
        .operation-title:hover { color: var(--gold-light); }
        .operation-meta { color: var(--text-muted); font-size: .7rem; margin-top: .2rem; }
        .operation-team { display: flex; align-items: center; gap: .3rem; margin: .2rem 0; font-size: .74rem; }
        .leader-star { color: var(--gold-bright); }
        .role-chip, .security-chip { display: inline-block; border-radius: 999px; padding: .12rem .4rem; background: rgba(212,175,55,.1); color: var(--gold-light); font-size: .63rem; white-space: nowrap; }
        .security-chip { margin: .1rem; background: rgba(59,130,246,.12); color: #93C5FD; }
        .company-financial-card { margin-top: 1.25rem; }
        .company-financial-summary { display: flex; flex-wrap: wrap; gap: 2rem; padding: .3rem 0 1rem; }
        .financial-value { font-size: 1.25rem; font-weight: 800; color: var(--gold-light); }
        .company-payout-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: .5rem; }
        .company-payout-item { display: flex; justify-content: space-between; gap: .5rem; padding: .6rem; border: 1px solid var(--border); border-radius: 9px; color: var(--text-muted); font-size: .72rem; }
        .company-payout-item strong { color: var(--text); }

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