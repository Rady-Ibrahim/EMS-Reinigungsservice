<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Kundenabrechnung</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #111827; }
        h2 { font-size: 14px; margin: 28px 0 10px; color: #111827; border-bottom: 2px solid #2563eb; padding-bottom: 4px; }
        .meta { color: #6b7280; font-size: 11px; }
        .header { border-bottom: 3px solid #2563eb; padding-bottom: 12px; margin-bottom: 20px; }
        .company { font-size: 12px; font-weight: bold; color: #2563eb; }
        .grid { display: flex; gap: 40px; }
        .block { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; }
        .block strong { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { text-align: left; background: #eff6ff; color: #1e3a8a; padding: 7px 8px; border-bottom: 2px solid #bfdbfe; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
        td { padding: 7px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
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
        <h1>Monatsabrechnung</h1>
        <div class="meta">Zeitraum: {{ \Carbon\CarbonImmutable::create($year, $month, 1)->locale('de')->translatedFormat('F Y') }}</div>
    </div>

    <div class="block">
        <strong>Kunde</strong>
        {{ $customer->name }}
    </div>

    <h2>Extra-Aufträge</h2>
    @if ($orders->isEmpty())
        <p class="meta">Keine Aufträge in diesem Zeitraum.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Auftrag</th>
                    <th>Standort</th>
                    <th>Beginn</th>
                    <th class="num">Einsätze</th>
                    <th class="num">Bezahlte Std.</th>
                </tr>
            </thead>
            <tbody>
                <?php $ordersTotal = 0; $paidTotal = 0; ?>
                @foreach ($orders as $order)
                    <?php
                        $paid = $order->executions->sum(fn($e) => ($e->work_minutes ?? 0) / 60);
                        $paidTotal += $paid;
                        $ordersTotal += 1;
                    ?>
                    <tr>
                        <td>{{ $order->title }}</td>
                        <td>{{ $order->location?->name ?? '—' }}</td>
                        <td>{{ $order->scheduled_date?->format('d.m.Y') ?? '—' }}</td>
                        <td class="num">{{ $order->executions->count() }}</td>
                        <td class="num">{{ number_format($paid, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="3">Summe</td>
                    <td class="num">{{ $ordersTotal }}</td>
                    <td class="num">{{ number_format($paidTotal, 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="footer">
        EMS Reinigungsservice · Erstellt am {{ now()->format('d.m.Y H:i') }}
    </div>
</body>
</html>