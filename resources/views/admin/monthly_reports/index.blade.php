@extends('admin.layouts.app')

@section('title', __('monthly_index.title'))
@section('content')
    <div class="card-header">
        <h1>{{ __('monthly_index.title') }}</h1>
    </div>

    <form method="GET" action="{{ route('admin.monthly-reports.index') }}" style="margin-bottom:.75rem;">
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
            <select name="month" style="width:auto;">
                @foreach($months as $monthOption)
                    <option value="{{ $monthOption['value'] }}" {{ (int) $month === $monthOption['value'] ? 'selected' : '' }}>
                        {{ $monthOption['label'] }}
                    </option>
                @endforeach
            </select>
            <select name="year" style="width:auto;">
                @foreach($years as $yearOption)
                    <option value="{{ $yearOption }}" {{ (int) $year === $yearOption ? 'selected' : '' }}>{{ $yearOption }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">{{ __('monthly_index.show') }}</button>
            <a href="{{ route('admin.monthly-reports.export-excel', ['year' => $year, 'month' => $month]) }}"
               class="btn btn-secondary btn-sm" style="margin-left:auto;">
                {{ __('monthly_index.export_excel') }}
            </a>
            <a href="{{ route('admin.monthly-reports.discrepancies', ['year' => $year, 'month' => $month]) }}"
               class="btn btn-secondary btn-sm">{{ __('monthly_index.discrepancies') }}</a>
        </div>
    </form>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>{{ __('monthly_index.employee') }}</th>
                    <th class="num">{{ __('monthly_index.fix_paired') }}</th>
                    <th class="num">{{ __('monthly_index.extra_work') }}</th>
                    <th class="num">{{ __('monthly_index.extra_travel') }}</th>
                    <th class="num">{{ __('monthly_index.total_paired') }}</th>
                    <th class="num">{{ __('messages.report.revenue') }}</th>
                    <th class="num">{{ __('messages.report.employee_cost') }}</th>
                    <th class="num">{{ __('messages.report.profit') }}</th>
                    <th>{{ __('monthly_index.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $report)
                    <tr>
                        <td>
                            <a href="{{ route('admin.monthly-reports.show', $report) }}">
                                {{ $report->employee?->name ?? '—' }}
                            </a>
                        </td>
                        <td class="num">{{ number_format((float) $report->fix_paid_hours, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $report->extra_work_hours, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $report->extra_travel_hours, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $report->total_paid_hours, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $report->revenue, 2, ',', '.') }} €</td>
                        <td class="num">{{ number_format((float) $report->employee_cost, 2, ',', '.') }} €</td>
                        <td class="num">
                            @php $p = (float) $report->profit; @endphp
                            <span class="badge {{ $p >= 0 ? 'badge-green' : 'badge-red' }}">
                                {{ number_format($p, 2, ',', '.') }} €
                            </span>
                        </td>
                        <td>
                            @if($report->isApproved())
                                <span class="badge badge-green">{{ __('monthly_index.approved') }}</span>
                            @else
                                <span class="badge badge-yellow">{{ __('monthly_index.draft') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9">{{ __('monthly_index.empty') }}</td></tr>
                @endforelse
            </tbody>
            @if($reports->isNotEmpty())
                @php
                    $sumRevenue = (float) $reports->sum(fn($r) => (float) $r->revenue);
                    $sumCost    = (float) $reports->sum(fn($r) => (float) $r->employee_cost);
                    $sumProfit  = (float) $reports->sum(fn($r) => (float) $r->profit);
                @endphp
                <tfoot>
                    <tr class="tot-row">
                        <td><strong>{{ __('monthly_index.sum_month') }}</strong></td>
                        <td colspan="4"></td>
                        <td class="num"><strong>{{ number_format($sumRevenue, 2, ',', '.') }} €</strong></td>
                        <td class="num"><strong>{{ number_format($sumCost, 2, ',', '.') }} €</strong></td>
                        <td class="num">
                            <span class="badge {{ $sumProfit >= 0 ? 'badge-green' : 'badge-red' }}">
                                {{ number_format($sumProfit, 2, ',', '.') }} €
                            </span>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endsection