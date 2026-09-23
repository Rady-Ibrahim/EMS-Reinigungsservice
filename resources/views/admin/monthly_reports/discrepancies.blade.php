@extends('admin.layouts.app')

@section('title', 'Abweichungsanalyse')
@section('content')
    <div class="card-header">
        <h1>Abweichungsanalyse</h1>
        <div class="meta">
            Ist-Stunden vs. bezahlte/geplante Stunden ·
            {{ \Carbon\CarbonImmutable::create($year, $month, 1)->locale('de')->translatedFormat('F Y') }}
        </div>
    </div>

    <form method="GET" action="{{ route('admin.monthly-reports.discrepancies') }}" style="margin-bottom:.75rem;">
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
            <input type="number" name="month" min="1" max="12" value="{{ $month }}" style="width:90px;">
            <input type="number" name="year" min="2020" max="2099" value="{{ $year }}" style="width:110px;">
            <button type="submit" class="btn btn-secondary btn-sm">Filtern</button>
            <a href="{{ route('admin.monthly-reports.index', ['year' => $year, 'month' => $month]) }}"
               class="btn btn-secondary btn-sm">Zurück</a>
        </div>
    </form>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Mitarbeiter</th>
                    <th class="num">Geplant (Std)</th>
                    <th class="num">Bezahlt (Std)</th>
                    <th class="num">Ist (Std)</th>
                    <th class="num">Bilanz (bezahlte − geplante)</th>
                    <th>Abweichung</th>
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
                                <span class="badge badge-red">Überbezahlung</span>
                            @elseif($row['balance'] < 0)
                                <span class="badge badge-yellow">Unterbezahlung</span>
                            @else
                                <span class="badge badge-green">Passend</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Keine Daten für diesen Zeitraum.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection