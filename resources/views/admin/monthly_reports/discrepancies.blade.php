@extends('admin.layouts.app')

@section('title', __('monthly_index.discrepancies'))
@section('content')
    <div class="card-header">
        <h1>{{ __('monthly_index.discrepancies') }}</h1>
        <div class="meta">
            {{ __('monthly_index.discrepancy_meta') }} ·
            {{ \Carbon\CarbonImmutable::create($year, $month, 1)->locale('de')->translatedFormat('F Y') }}
        </div>
    </div>

    <form method="GET" action="{{ route('admin.monthly-reports.discrepancies') }}" style="margin-bottom:.75rem;">
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
            <input type="number" name="month" min="1" max="12" value="{{ $month }}" style="width:90px;">
            <input type="number" name="year" min="2020" max="2099" value="{{ $year }}" style="width:110px;">
            <button type="submit" class="btn btn-secondary btn-sm">{{ __('monthly_index.filter') }}</button>
            <a href="{{ route('admin.monthly-reports.index', ['year' => $year, 'month' => $month]) }}"
               class="btn btn-secondary btn-sm">{{ __('monthly_index.back') }}</a>
        </div>
    </form>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>{{ __('monthly_index.employee') }}</th>
                    <th class="num">{{ __('monthly_index.planned') }}</th>
                    <th class="num">{{ __('monthly_index.paid') }}</th>
                    <th class="num">{{ __('monthly_index.actual') }}</th>
                    <th class="num">{{ __('monthly_index.balance') }}</th>
                    <th>{{ __('monthly_index.discrepancy') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($discrepancies as $row)
                    <tr>
                        <td>{{ $row['employee_name'] }}</td>
                        <td class="num">{{ number_format((float) $row['planned_hours'], 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $row['paid_hours'], 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $row['actual_hours'], 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $row['balance'], 2, ',', '.') }}</td>
                        <td>
                            @if($row['balance'] > 0)
                                <span class="badge badge-red">{{ __('monthly_index.overpaid') }}</span>
                            @elseif($row['balance'] < 0)
                                <span class="badge badge-yellow">{{ __('monthly_index.underpaid') }}</span>
                            @else
                                <span class="badge badge-green">{{ __('monthly_index.matched') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">{{ __('monthly_index.empty_discrepancies') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection