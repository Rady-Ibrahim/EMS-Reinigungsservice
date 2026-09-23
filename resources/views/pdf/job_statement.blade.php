<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Leistungsnachweis</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #111827; }
        .meta { color: #6b7280; font-size: 11px; }
        .header { border-bottom: 3px solid #2563eb; padding-bottom: 12px; margin-bottom: 20px; }
        .company { font-size: 12px; font-weight: bold; color: #2563eb; }
        .grid { display: flex; gap: 40px; }
        .block { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; }
        .block strong { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { text-align: left; background: #eff6ff; color: #1e3a8a; padding: 7px 8px; border-bottom: 2px solid #bfdbfe; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
        td { padding: 7px 8px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) td { background: #f9fafb; }
        .total td { font-weight: bold; background: #f3f4f6 !important; border-top: 2px solid #d1d5db; }
        .num { text-align: right; }
        .footer { margin-top: 30px; color: #9ca3af; font-size: 9px; text-align: center; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 9px; font-weight: bold; }
        .badge.done { background: #dcfce7; color: #166534; }
        .badge.open { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">EMS Reinigungsservice</div>
        <h1>Leistungsnachweis</h1>
        <div class="meta">
            {{ $type === 'fix_object' ? 'Fixobjekt' : 'Extra-Auftrag' }} ·
            {{ \Carbon\CarbonImmutable::create($year, $month, 1)->locale('de')->translatedFormat('F Y') }}
        </div>
    </div>

    <div class="grid">
        <div class="block" style="flex:1">
            <strong>Auftrag</strong>
            {{ $job->title }}
        </div>
        <div class="block" style="flex:1">
            <strong>Kunde</strong>
            {{ $job->customer?->name ?? '—' }}
        </div>
        <div class="block" style="flex:1">
            <strong>Standort</strong>
            {{ $job->location?->name ?? '—' }}
        </div>
    </div>

    <div class="grid">
        <div class="block" style="flex:1">
            <strong>Beginn</strong>
            {{ $type === 'fix_object'
                ? ($job->schedules->min('scheduled_date')?->format('d.m.Y') ?? '—')
                : ($job->scheduled_date?->format('d.m.Y') ?? '—') }}
        </div>
        <div class="block" style="flex:1">
            <strong>Ende</strong>
            {{ $type === 'fix_object' ? ($job->schedules->max('scheduled_date')?->format('d.m.Y') ?? '—') : ($job->scheduled_date?->format('d.m.Y') ?? '—') }}
        </div>
        <div class="block" style="flex:1">
            <strong>Status</strong>
            @if ($completed)
                <span class="badge done">Abgeschlossen</span>
            @else
                <span class="badge open">Offen</span>
            @endif
        </div>
    </div>

    <h2 style="font-size:14px; border-bottom:2px solid #2563eb; padding-bottom:4px;">Einsätze im Monat</h2>
    @if ($executions->isEmpty())
        <p class="meta">Keine Einsätze in diesem Zeitraum.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Mitarbeiter</th>
                    <th>Datum</th>
                    @if ($type === 'fix_object')
                        <th class="num">Gepaart (Std)</th>
                        <th class="num">Tatsächlich (Std)</th>
                    @else
                        <th class="num">Arbeitszeit (Std)</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($executions as $exec)
                    <tr>
                        <td>{{ $exec->employee?->name ?? '—' }}</td>
                        <td>{{ $type === 'fix_object' ? ($exec->actual_start?->format('d.m.Y') ?? '—') : ($exec->work_start?->format('d.m.Y') ?? '—') }}</td>
                        @if ($type === 'fix_object')
                            <td class="num">{{ number_format((float) $exec->contract_hours_applied, 2, ',', '.') }}</td>
                            <td class="num">{{ number_format(($exec->actualDurationMinutes() ?? 0) / 60, 2, ',', '.') }}</td>
                        @else
                            <td class="num">{{ number_format(($exec->work_minutes ?? 0) / 60, 2, ',', '.') }}</td>
                        @endif
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="{{ $type === 'fix_object' ? 3 : 2 }}">Summe bezahlt</td>
                    <td class="num">{{ number_format($totalPaid, 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="footer">
        EMS Reinigungsservice · Erstellt am {{ now()->format('d.m.Y H:i') }}
    </div>
</body>
</html>