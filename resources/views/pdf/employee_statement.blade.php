<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Mitarbeiter-Nachweis</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #111827; }
        .meta { color: #6b7280; font-size: 11px; }
        .header { border-bottom: 3px solid #2563eb; padding-bottom: 12px; margin-bottom: 20px; }
        .company { font-size: 12px; font-weight: bold; color: #2563eb; }
        .grid { display: flex; gap: 40px; }
        .block { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; }
        .block strong { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #eff6ff; color: #1e3a8a; padding: 7px 8px; border-bottom: 2px solid #bfdbfe; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
        td { padding: 7px 8px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) td { background: #f9fafb; }
        tfoot td { font-weight: bold; background: #f3f4f6 !important; border-top: 2px solid #d1d5db; }
        .num { text-align: right; }
        .footer { margin-top: 30px; color: #9ca3af; font-size: 9px; text-align: center; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 9px; font-weight: bold; }
        .badge.approved { background: #dcfce7; color: #166534; }
        .badge.draft { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">EMS Reinigungsservice</div>
        <h1>Monatlicher Stundenbericht</h1>
        <div class="meta">Zeitraum: {{ $report->periodLabel() }} · Stand: {{ now()->format('d.m.Y H:i') }}</div>
    </div>

    <div class="grid">
        <div class="block" style="flex:1">
            <strong>Mitarbeiter</strong>
            {{ $employee->name }}
        </div>
        <div class="block" style="flex:1">
            <strong>Status</strong>
            @if ($report->isApproved())
                <span class="badge approved">Freigegeben</span>
            @else
                <span class="badge draft">Entwurf</span>
            @endif
        </div>
        <div class="block" style="flex:1">
            <strong>Freigegeben von</strong>
            {{ $report->approver?->name ?? '—' }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Position</th>
                <th class="num">Std.</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Fixobjekte · bezahlte Stunden (vertraglich)</td>
                <td class="num">{{ number_format((float) $report->fix_paid_hours, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Extra-Aufträge · Arbeitszeit</td>
                <td class="num">{{ number_format((float) $report->extra_work_hours, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Extra-Aufträge · bezahlte Fahrtzeit</td>
                <td class="num">{{ number_format((float) $report->extra_travel_hours, 2, ',', '.') }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td>Gesamt bezahlte Stunden</td>
                <td class="num">{{ number_format((float) $report->total_paid_hours, 2, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        EMS Reinigungsservice · Dieses Dokument ist verbindlich nach Freigabe des Berichts.
    </div>
</body>
</html>