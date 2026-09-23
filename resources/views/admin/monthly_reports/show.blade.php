@extends('admin.layouts.app')

@section('title', 'Monatsbericht')
@section('content')
    <div class="card-header">
        <h1>Monatsbericht — {{ $monthlyReport->employee?->name ?? '—' }}</h1>
        <div class="meta">
            {{ $monthlyReport->periodLabel() }} ·
            @if($monthlyReport->isApproved())
                <span class="badge badge-green">Freigegeben</span>
            @else
                <span class="badge badge-yellow">Entwurf</span>
            @endif
        </div>
    </div>

    @if($monthlyReport->isApproved())
        <div class="alert alert-success">
            Freigegeben am {{ $monthlyReport->approved_at->format('d.m.Y H:i') }}
            von {{ $monthlyReport->approver?->name ?? '—' }}.
            Dieser Bericht ist eingefroren und ändert sich nicht mehr.
        </div>
    @endif

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Position</th>
                    <th class="num">Stunden</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Fixobjekte · bezahlte Stunden (vertraglich)</td><td class="num">{{ number_format((float) $monthlyReport->fix_paid_hours, 2, ',', '.') }}</td></tr>
                <tr><td>Fixobjekte · tatsächlich gearbeitet</td><td class="num">{{ number_format((float) $monthlyReport->fix_actual_hours, 2, ',', '.') }}</td></tr>
                <tr><td>Extra-Aufträge · Arbeitszeit</td><td class="num">{{ number_format((float) $monthlyReport->extra_work_hours, 2, ',', '.') }}</td></tr>
                <tr><td>Extra-Aufträge · bezahlte Fahrtzeit</td><td class="num">{{ number_format((float) $monthlyReport->extra_travel_hours, 2, ',', '.') }}</td></tr>
                <tr><td>Extra-Aufträge · tatsächlich (Arbeit + Fahrt)</td><td class="num">{{ number_format((float) $monthlyReport->extra_actual_hours, 2, ',', '.') }}</td></tr>
                <tr style="font-weight:bold;background:#f3f4f6;border-top:2px solid #d1d5db;">
                    <td>Gesamt bezahlte Stunden</td>
                    <td class="num">{{ number_format((float) $monthlyReport->total_paid_hours, 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @if($lineItems->isNotEmpty())
        <h2 style="font-size:1rem;margin:1.5rem 0 .5rem;">Einzelnachweise</h2>
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Auftrag</th>
                        <th>Kunde</th>
                        <th class="num">Bezahlt (Std)</th>
                        <th class="num">Tatsächlich (Std)</th>
                        <th class="num">Fahrt (Std)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lineItems as $item)
                        <tr>
                            <td>{{ \Carbon\CarbonImmutable::parse($item['date'])->format('d.m.Y') }}</td>
                            <td>
                                {{ $item['label'] }}
                                <span class="badge {{ $item['job_type'] === 'fix_object' ? 'badge-blue' : 'badge-yellow' }}">
                                    {{ $item['job_type'] === 'fix_object' ? 'Fix' : 'Extra' }}
                                </span>
                            </td>
                            <td>{{ $item['customer'] }}</td>
                            <td class="num">{{ number_format((float) $item['paid_hours'], 2, ',', '.') }}</td>
                            <td class="num">{{ number_format((float) $item['actual_hours'], 2, ',', '.') }}</td>
                            <td class="num">{{ number_format((float) $item['travel_hours'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div style="margin-top:1rem;display:flex;gap:.5rem;">
        <a href="{{ route('admin.monthly-reports.export-pdf', $monthlyReport) }}"
           class="btn btn-secondary btn-sm">Als PDF exportieren</a>
        @if(!$monthlyReport->isApproved())
            <form method="POST" action="{{ route('admin.monthly-reports.approve', $monthlyReport) }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm"
                        onclick="return confirm('Monatsbericht freigeben und einfrieren? Die Nummer wird danach nicht mehr geändert.');">
                    Freigeben &amp; einfrieren
                </button>
            </form>
        @endif
        <a href="{{ route('admin.monthly-reports.index', ['year' => $monthlyReport->year, 'month' => $monthlyReport->month]) }}"
           class="btn btn-secondary btn-sm">Zurück</a>
    </div>
@endsection