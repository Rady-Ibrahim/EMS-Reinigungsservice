@extends('admin.layouts.app')

@section('title', __('messages.report.title').' — '.($monthlyReport->employee?->name ?? '—'))
@section('content')
    @php
        $total   = (float) $monthlyReport->total_paid_hours;
        $fix     = (float) $monthlyReport->fix_paid_hours;
        $extra   = (float) $monthlyReport->extra_work_hours + (float) $monthlyReport->extra_travel_hours;
        $travel  = (float) $monthlyReport->extra_travel_hours;
        $actual  = (float) $monthlyReport->fix_actual_hours + (float) $monthlyReport->extra_actual_hours;
        $count   = $lineItems->count();

        $segments = [];
        if ($total > 0) {
            $segments[] = ['label' => __('messages.cal.layers.fix').' ('.__('messages.report.vertraglich').')', 'value' => $fix,    'color' => '#D4AF37', 'pct' => $fix / $total * 100];
            $segments[] = ['label' => __('messages.cal.layers.extra').' · '.__('messages.report.work_time_short'), 'value' => max((float) $monthlyReport->extra_work_hours, 0), 'color' => '#10B981', 'pct' => max((float) $monthlyReport->extra_work_hours, 0) / $total * 100];
            $segments[] = ['label' => __('messages.cal.layers.extra').' · '.__('messages.report.travel_time_short'), 'value' => $travel,  'color' => '#8B5CF6', 'pct' => $travel / $total * 100];
        }
        $degrees = 0;
        $gradient = [];
        foreach ($segments as $seg) {
            $from = $degrees;
            $degrees += $seg['pct'] * 0.01 * 360;
            $gradient[] = $seg['color'].' '.$from.'deg '.min($degrees, 360).'deg';
        }
        $conic = $gradient ? 'conic-gradient('.implode(', ', $gradient).')' : 'conic-gradient(#2A2E39 0deg 360deg)';

        $missing = $lineItems->filter(fn($i) => (float) $i['actual_hours'] < (float) $i['paid_hours'])->count();
        $avg = $count > 0 ? $total / $count : 0;

        $kpis = [
            ['label' => __('messages.report.kpi_total'),   'value' => number_format($total, 2, ',', '.'), 'unit' => 'h', 'ico' => '🕒',  'accent' => '#D4AF37'],
            ['label' => __('messages.report.kpi_count'),   'value' => $count,                             'unit' => '',  'ico' => '📋',  'accent' => '#3B82F6'],
            ['label' => __('messages.report.kpi_avg'),     'value' => number_format($avg, 2, ',', '.'),   'unit' => 'h', 'ico' => '📊',  'accent' => '#8B5CF6'],
            ['label' => __('messages.report.kpi_missing'), 'value' => $missing,                           'unit' => '',  'ico' => '⚠️',  'accent' => $missing > 0 ? '#EF4444' : '#10B981'],
        ];
    @endphp

    <div class="mr-header">
        <div>
            <h1>{{ __('messages.report.title') }} — {{ $monthlyReport->employee?->name ?? '—' }}</h1>
            <div class="meta">
                {{ $monthlyReport->periodLabel() }} ·
                @if($monthlyReport->isApproved())
                    <span class="badge badge-green">{{ __('messages.report.freigegeben') }}</span>
                @else
                    <span class="badge badge-yellow">{{ __('messages.report.entwurf') }}</span>
                @endif
            </div>
        </div>
        <div class="actions">
            <a href="{{ route('admin.monthly-reports.export-pdf', $monthlyReport) }}" class="btn-gold-inline">⬇ {{ __('messages.report.export_pdf') }}</a>
            <a href="{{ route('admin.monthly-reports.export-excel', ['year' => $monthlyReport->year, 'month' => $monthlyReport->month]) }}" class="btn-gold-outline-inline">⬇ {{ __('messages.report.export_excel') }}</a>
        </div>
    </div>

    @if($monthlyReport->isApproved())
        <div class="alert alert-success">
            {{ __('messages.report.approved_at') }} {{ $monthlyReport->approved_at->format('d.m.Y H:i') }}
            {{ __('messages.report.approved_by') }} {{ $monthlyReport->approver?->name ?? '—' }}.
            {{ __('messages.report.frozen_line') }}
        </div>
    @endif

    {{-- KPI cards --}}
    <div class="kpi-grid">
        @foreach($kpis as $kpi)
            <div class="kpi-card" style="--accent: {{ $kpi['accent'] }}">
                <div class="kpi-ico">{{ $kpi['ico'] }}</div>
                <div class="kpi-body">
                    <div class="kpi-value">{{ $kpi['value'] }}<span class="kpi-unit">{{ $kpi['unit'] }}</span></div>
                    <div class="kpi-label">{{ $kpi['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Two-column: donut + totals --}}
    <div class="mr-cols">
        <div class="card" style="flex:1;">
            <div class="card-title" style="margin-bottom:.9rem">{{ __('messages.report.donut_title') }}</div>
            <div class="donut-wrap">
                <div class="donut" style="background: {{ $conic }};">
                    <div class="donut-inner">
                        <div class="donut-value">{{ number_format($total, 1, ',', '.') }}</div>
                        <div class="donut-label">{{ __('messages.report.hours_total_label') }}</div>
                    </div>
                </div>
                <div class="donut-legend">
                    @forelse($segments as $seg)
                        <div class="lg-item">
                            <span class="lg-dot" style="background:{{ $seg['color'] }}"></span>
                            <span class="lg-label">{{ $seg['label'] }}</span>
                            <span class="lg-value">{{ number_format($seg['value'], 2, ',', '.') }} h</span>
                            <span class="lg-pct">{{ number_format($seg['pct'], 1, ',', '.') }}%</span>
                        </div>
                    @empty
                        <div class="lg-item"><span class="lg-label">{{ __('messages.report.no_hours') }}</span></div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="card" style="flex:1;">
            <div class="card-title" style="margin-bottom:.9rem">{{ __('messages.report.overview') }}</div>
            <table>
                <tbody>
                    <tr><td>{{ __('messages.report.fix_paid') }}</td><td class="num">{{ number_format($fix, 2, ',', '.') }} h</td></tr>
                    <tr><td>{{ __('messages.report.fix_actual') }}</td><td class="num">{{ number_format((float) $monthlyReport->fix_actual_hours, 2, ',', '.') }} h</td></tr>
                    <tr><td>{{ __('messages.report.extra_work') }}</td><td class="num">{{ number_format((float) $monthlyReport->extra_work_hours, 2, ',', '.') }} h</td></tr>
                    <tr><td>{{ __('messages.report.extra_travel') }}</td><td class="num">{{ number_format($travel, 2, ',', '.') }} h</td></tr>
                    <tr><td>{{ __('messages.report.extra_actual') }}</td><td class="num">{{ number_format((float) $monthlyReport->extra_actual_hours, 2, ',', '.') }} h</td></tr>
                    <tr class="tot-row">
                        <td><strong>{{ __('messages.report.total_paid') }}</strong></td>
                        <td class="num"><strong>{{ number_format($total, 2, ',', '.') }} h</strong></td>
                    </tr>
                    <tr class="actual-row">
                        <td>{{ __('messages.report.delta') }}</td>
                        <td class="num">
                            @php $diff = $actual - $total; @endphp
                            <span class="{{ $diff >= 0 ? 'badge badge-green' : 'badge badge-red' }}">
                                {{ ($diff >= 0 ? '+' : '') . number_format($diff, 2, ',', '.') }} h
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Profitability · administrator only --}}
    @if(auth()->user()->isAdministrator())
        @php
            $revenue = (float) $monthlyReport->revenue;
            $empCost = (float) $monthlyReport->employee_cost;
            $profit  = (float) $monthlyReport->profit;
            $margin  = $monthlyReport->profitMarginPercent();
            $rate    = (float) $monthlyReport->hourly_cost_applied;
        @endphp
        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ __('messages.report.profitability') }}</span>
                @if($monthlyReport->isApproved())
                    <span class="badge badge-green">{{ __('messages.report.frozen_numbers') }}</span>
                @else
                    <span class="badge badge-yellow">{{ __('messages.report.live_figures') }}</span>
                @endif
            </div>
            <table>
                <tbody>
                    <tr>
                        <td>{{ __('messages.report.revenue') }}</td>
                        <td class="num">{{ number_format($revenue, 2, ',', '.') }} €</td>
                    </tr>
                    <tr>
                        <td>
                            {{ __('messages.report.employee_cost') }}
                            <div class="meta">
                                {{ number_format($total, 2, ',', '.') }} h ×
                                {{ number_format($rate, 2, ',', '.') }} {{ __('messages.report.per_hour') }}
                            </div>
                        </td>
                        <td class="num">{{ number_format($empCost, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="tot-row">
                        <td><strong>{{ __('messages.report.profit') }}</strong></td>
                        <td class="num">
                            <strong>{{ number_format($profit, 2, ',', '.') }} €</strong>
                            <span class="badge {{ $profit >= 0 ? 'badge-green' : 'badge-red' }}">
                                {{ $profit >= 0 ? '+' : '' }}{{ number_format($profit, 2, ',', '.') }} €
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>{{ __('messages.report.profit_margin') }}</td>
                        <td class="num">
                            @if($margin === null)
                                <span class="meta">—</span>
                            @else
                                <span class="badge {{ $margin >= 0 ? 'badge-green' : 'badge-red' }}">
                                    {{ number_format($margin, 1, ',', '.') }} %
                                </span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="meta" style="margin-top:.8rem">{{ __('messages.report.revenue_note') }}</div>
        </div>
    @endif

    {{-- Line items --}}
    @if($lineItems->isNotEmpty())
        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ __('messages.report.line_items') }} <span class="mini-pill">{{ $count }} {{ __('messages.report.count_ops') }}</span></span>
                @if($missing > 0)
                    <span class="badge badge-yellow">{{ $missing }} {{ __('messages.report.missing') }}</span>
                @else
                    <span class="badge badge-green">{{ __('messages.report.complete') }}</span>
                @endif
            </div>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('messages.report.th_date') }}</th>
                        <th>{{ __('messages.report.th_order') }}</th>
                        <th>{{ __('messages.report.th_customer') }}</th>
                        <th class="num">{{ __('messages.report.th_paid') }}</th>
                        <th class="num">{{ __('messages.report.th_actual') }}</th>
                        <th class="num">{{ __('messages.report.th_travel') }}</th>
                        <th>{{ __('messages.report.th_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lineItems as $item)
                        @php
                            $paid   = (float) $item['paid_hours'];
                            $actual = (float) $item['actual_hours'];
                            $complete = $paid <= 0.0001 || $actual >= $paid - 0.0049;
                        @endphp
                        <tr>
                            <td>{{ \Carbon\CarbonImmutable::parse($item['date'])->format('d.m.Y') }}</td>
                            <td>
                                {{ $item['label'] }}
                                <span class="badge {{ $item['job_type'] === 'fix_object' ? 'badge-blue' : 'badge-yellow' }}" style="margin-inline-start:.3rem;font-size:.62rem">
                                    {{ $item['job_type'] === 'fix_object' ? __('messages.report.badge_fix') : __('messages.report.badge_extra') }}
                                </span>
                            </td>
                            <td>{{ $item['customer'] }}</td>
                            <td class="num">{{ number_format($paid, 2, ',', '.') }}</td>
                            <td class="num">{{ number_format($actual, 2, ',', '.') }}</td>
                            <td class="num">{{ number_format((float) $item['travel_hours'], 2, ',', '.') }}</td>
                            <td>
                                @if($complete)
                                    <span class="badge badge-green">{{ __('messages.report.complete') }}</span>
                                @else
                                    <span class="badge badge-yellow">{{ __('messages.report.incomplete') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div style="margin-top:1rem;display:flex;gap:.5rem;align-items:center;">
        @if(!$monthlyReport->isApproved())
            <form method="POST" action="{{ route('admin.monthly-reports.approve', $monthlyReport) }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm"
                        onclick="return confirm('{{ __('messages.report.approve_confirm') }}');">
                    {{ __('messages.report.approve') }}
                </button>
            </form>
        @endif
        <a href="{{ route('admin.monthly-reports.index', ['year' => $monthlyReport->year, 'month' => $monthlyReport->month]) }}"
           class="btn btn-secondary btn-sm">← {{ __('messages.report.back') }}</a>
    </div>

    <style>
        a { text-decoration: none; }
        .mr-header {
            display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;
            margin-bottom: 1.2rem;
        }
        .mr-header h1 { margin-bottom: .3rem; }
        .btn-gold-inline {
            display: inline-flex; align-items: center; padding: .55rem 1.05rem; border-radius: 11px;
            background: linear-gradient(180deg, var(--gold-light), var(--gold) 55%, #B08A26);
            color: #141008; font-weight: 800; font-size: .8rem;
            box-shadow: 0 6px 20px rgba(212,175,55,.3); border: none; cursor: pointer;
        }
        .btn-gold-inline:hover { filter: brightness(1.08); transform: translateY(-1px); }
        .btn-gold-outline-inline {
            display: inline-flex; align-items: center; padding: .55rem 1.05rem; border-radius: 11px;
            background: transparent; color: var(--gold-light); border: 1px solid rgba(212,175,55,.5);
            font-weight: 700; font-size: .8rem; cursor: pointer;
        }
        .btn-gold-outline-inline:hover { background: rgba(212,175,55,.1); }

        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.25rem; }
        @media (max-width: 900px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
        .kpi-card {
            display: flex; align-items: center; gap: .9rem;
            background: linear-gradient(180deg, var(--panel), #13161C);
            border: 1px solid var(--border); border-top: 2px solid var(--accent);
            border-radius: 16px; padding: 1rem 1.15rem;
            box-shadow: 0 8px 26px rgba(0,0,0,.35);
        }
        .kpi-ico { font-size: 1.5rem; width: 48px; height: 48px; flex: 0 0 48px; background: rgba(212,175,55,.1); border: 1px solid rgba(212,175,55,.25); border-radius: 13px; display: flex; align-items: center; justify-content: center; }
        .kpi-value { font-size: 1.55rem; font-weight: 800; color: var(--accent); line-height: 1.05; }
        .kpi-unit { font-size: .8rem; font-weight: 600; color: var(--text-muted); }
        .kpi-label { font-size: .8rem; font-weight: 700; color: var(--text); margin-top: .3rem; letter-spacing: .03em; }

        .mr-cols { display: flex; gap: 1.25rem; align-items: stretch; }
        @media (max-width: 980px) { .mr-cols { flex-direction: column; } }

        .donut-wrap { display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap; }
        .donut {
            position: relative; width: 185px; height: 185px; border-radius: 50%;
            box-shadow: 0 0 30px rgba(212,175,55,.15), inset 0 0 0 6px #101217;
            flex: 0 0 185px;
        }
        .donut-inner {
            position: absolute; inset: 0; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: .1rem;
        }
        .donut-inner::before { content: ""; position: absolute; inset: 26px; border-radius: 50%; background: #12141A; }
        .donut-value { position: relative; font-size: 1.7rem; font-weight: 800; color: var(--gold-light); text-shadow: 0 0 18px rgba(212,175,55,.4); }
        .donut-label { position: relative; font-size: .68rem; color: var(--text-faint); letter-spacing: .08em; text-transform: uppercase; }

        .donut-legend { flex: 1; min-width: 220px; display: flex; flex-direction: column; gap: .55rem; }
        .lg-item { display: flex; align-items: center; gap: .6rem; font-size: .82rem; }
        .lg-dot { width: 11px; height: 11px; border-radius: 50%; flex: 0 0 11px; box-shadow: 0 0 8px rgba(0,0,0,.5); }
        .lg-label { color: var(--text-muted); flex: 1; }
        .lg-value { color: var(--text); font-weight: 700; font-variant-numeric: tabular-nums; }
        .lg-pct { color: var(--gold-light); font-weight: 700; min-width: 52px; text-align: end; font-variant-numeric: tabular-nums; }

        .tot-row td { background: rgba(212,175,55,.09); border-top: 1px solid var(--border-gold); }
        .tot-row td:first-child { border-start-start-radius: 10px; border-end-start-radius: 10px; }
        .tot-row td:last-child { border-start-end-radius: 10px; border-end-end-radius: 10px; }
        .actual-row td { border-bottom: none; }

        .mini-pill {
            display: inline-block; font-size: .62rem; font-weight: 700; letter-spacing: .06em;
            padding: .1rem .45rem; border-radius: 999px; vertical-align: middle;
            background: rgba(59,130,246,.15); color: #93C5FD; border: 1px solid rgba(59,130,246,.3);
            text-transform: uppercase;
        }
    </style>
@endsection