<?php

namespace App\Exports;

use App\Models\MonthlyReport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Payroll-ready Excel export of approved monthly reports.
 * One row per employee; only approved (frozen) periods are exported,
 * which guarantees the numbers match the frozen payroll snapshot.
 */
class MonthlyReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents
{
    public function __construct(
        public readonly Collection $reports,
        public readonly int $year,
        public readonly int $month,
    ) {
    }

    public function collection(): Collection
    {
        return $this->reports;
    }

    public function headings(): array
    {
        return [
            'Monatsbericht',
            'Mitarbeiter',
            'Fix gepaart (Std)',
            'Fix tatsächlich (Std)',
            'Extra Arbeit (Std)',
            'Extra Fahrt (Std)',
            'Extra gepaart (Std)',
            'Gesamt gepaart (Std)',
            'Status',
        ];
    }

    public function map($report): array
    {
        return [
            $this->periodLabel(),
            $report->employee?->name ?? '—',
            $report->fix_paid_hours,
            $report->fix_actual_hours,
            $report->extra_work_hours,
            $report->extra_travel_hours,
            $report->extra_paid_hours,
            $report->total_paid_hours,
            $report->isApproved() ? 'Freigegeben' : 'Entwurf',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                foreach (range('A', 'I') as $column) {
                    $event->sheet->getDelegate()->getColumnDimension($column)->setAutoSize(true);
                }
            },
        ];
    }

    private function periodLabel(): string
    {
        return \Carbon\CarbonImmutable::create($this->year, $this->month, 1)
            ->locale('de')
            ->translatedFormat('F Y');
    }
}