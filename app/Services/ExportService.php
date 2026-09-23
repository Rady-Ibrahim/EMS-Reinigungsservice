<?php

namespace App\Services;

use App\Exports\MonthlyReportExport;
use App\Models\Customer;
use App\Models\ExtraAuftrag;
use App\Models\MonthlyReport;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Financial export layer.
 *
 *  - Excel  : payroll-ready monthly report (one row per employee)
 *  - PDF    : customer / job statements, print-ready for invoices & audits
 */
class ExportService
{
    // ── Excel ──────────────────────────────────────────────────────────────

    /**
     * Download the payroll Excel for a given month.
     */
    public function monthlyReportExcel(Collection $reports, int $year, int $month): BinaryFileResponse
    {
        $fileName = sprintf('Monatsbericht_%s-%02d.xlsx', $year, $month);

        return Excel::download(new MonthlyReportExport($reports, $year, $month), $fileName);
    }

    // ── PDF (Statements) ───────────────────────────────────────────────────

    /**
     * Customer statement: every extra order for the customer within a month,
     * with paid hours and billed job totals.
     */
    public function customerStatementPdf(Customer $customer, int $year, int $month): Response
    {
        $orders = ExtraAuftrag::where('customer_id', $customer->id)
            ->whereYear('scheduled_date', $year)
            ->whereMonth('scheduled_date', $month)
            ->with(['executions', 'customer', 'location'])
            ->orderBy('planned_start')
            ->get();

        $pdf = Pdf::loadView('pdf.customer_statement', [
            'customer' => $customer,
            'year'     => $year,
            'month'    => $month,
            'orders'   => $orders,
        ])->setPaper('a4');

        return $pdf->stream(sprintf(
            'Abrechnung_%s_%s-%02d.pdf',
            str_replace([' '], '_', $customer->name),
            $year,
            $month
        ));
    }

    /**
     * Job statement: a single job (Fixobjekt or Extra-Auftrag) with all
     * employee work records for the given month.
     */
    public function jobStatementPdf(string $type, int $id, int $year, int $month): Response
    {
        if ($type === 'fix_object') {
            $job = \App\Models\FixObject::with(['customer', 'location', 'schedules'])->findOrFail($id);
            $executions = \App\Models\FixObjectExecution::whereIn('schedule_id', $job->schedules->pluck('id'))
                ->whereNotNull('actual_end')
                ->whereYear('actual_start', $year)
                ->whereMonth('actual_start', $month)
                ->with('employee')
                ->orderBy('actual_start')
                ->get();
            $totalPaid = $executions->sum(fn($e) => (float) $e->contract_hours_applied);
            $completed = false;
        } else {
            $job = ExtraAuftrag::with(['customer', 'location'])->findOrFail($id);
            $executions = $job->executions()
                ->whereNotNull('work_end')
                ->whereYear('work_start', $year)
                ->whereMonth('work_start', $month)
                ->with('employee')
                ->orderBy('work_start')
                ->get();
            $totalPaid = $executions->sum(fn($e) => ((float) $e->work_minutes) / 60);
            $completed = $job->status->isTerminal();
        }

        $pdf = Pdf::loadView('pdf.job_statement', [
            'type'       => $type,
            'job'        => $job,
            'year'       => $year,
            'month'      => $month,
            'executions' => $executions,
            'totalPaid'  => round($totalPaid, 2),
            'completed'  => $completed,
        ])->setPaper('a4');

        return $pdf->stream(sprintf(
            'Leistungsnachweis_%s_%s-%02d.pdf',
            str_replace(['/', ' '], '_', $job->title ?? $job->id),
            $year,
            $month
        ));
    }

    /**
     * Employee statement: every work record of one employee in a month —
     * the public human-readable counterpart of the monthly report.
     */
    public function employeeStatementPdf(MonthlyReport $report, User $employee): Response
    {
        $pdf = Pdf::loadView('pdf.employee_statement', [
            'report'   => $report,
            'employee' => $employee,
        ])->setPaper('a4');

        return $pdf->stream(sprintf(
            'Mitarbeiter_%s_%s-%02d.pdf',
            str_replace([' '], '_', $employee->name),
            $report->year,
            $report->month
        ));
    }
}