@extends('admin.layouts.app')

@section('title', 'Monatsberichte')
@section('content')
    <div class="card-header">
        <h1>Monatsberichte</h1>
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
            <button type="submit" class="btn btn-secondary btn-sm">Anzeigen</button>
            <a href="{{ route('admin.monthly-reports.export-excel', ['year' => $year, 'month' => $month]) }}"
               class="btn btn-secondary btn-sm" style="margin-left:auto;">
                Excel exportieren
            </a>
            <a href="{{ route('admin.monthly-reports.discrepancies', ['year' => $year, 'month' => $month]) }}"
               class="btn btn-secondary btn-sm">Abweichungsanalyse</a>
        </div>
    </form>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Mitarbeiter</th>
                    <th class="num">Fix gepaart</th>
                    <th class="num">Extra Arbeit</th>
                    <th class="num">Extra Fahrt</th>
                    <th class="num">Gesamt gepaart</th>
                    <th>Status</th>
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
                        <td>
                            @if($report->isApproved())
                                <span class="badge badge-green">Freigegeben</span>
                            @else
                                <span class="badge badge-yellow">Entwurf</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Keine Mitarbeiter gefunden.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection