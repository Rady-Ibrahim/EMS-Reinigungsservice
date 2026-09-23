<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use App\Models\User;
use App\Services\ExportService;
use App\Services\MonthlyReportEngineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\View\View;

class MonthlyReportController extends Controller
{
    public function __construct(
        private readonly MonthlyReportEngineService $engine,
        private readonly ExportService              $export
    ) {
    }

    // ── Report Overview ────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $request->validate([
            'year'  => ['nullable', 'integer', 'min:2020', 'max:2099'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $year  = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);

        $reports = $this->engine->employeesWithReports($year, $month)->sortBy('employee.name');
        $months  = collect(range(1, 12))->map(fn(int $m) => [
            'value' => $m,
            'label' => \Carbon\CarbonImmutable::create($year, $m, 1)->locale('de')->translatedFormat('F'),
        ]);
        $years = collect(range(now()->year - 2, now()->year))->sortDesc();

        return view('admin.monthly_reports.index', compact('reports', 'year', 'month', 'months', 'years'));
    }

    public function show(MonthlyReport $monthlyReport): View
    {
        $monthlyReport->load(['employee', 'approver']);

        $lineItems = collect($monthlyReport->snapshot['line_items'] ?? []);

        return view('admin.monthly_reports.show', compact('monthlyReport', 'lineItems'));
    }

    // ── Approval / Freeze ──────────────────────────────────────────────────

    public function approve(Request $request, MonthlyReport $monthlyReport): RedirectResponse
    {
        if ($monthlyReport->isApproved()) {
            return back()->withErrors(['error' => 'Der Monatsbericht ist bereits freigegeben.']);
        }

        try {
            $this->engine->approve($monthlyReport->employee, $monthlyReport->year, $monthlyReport->month, Auth::user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('admin.monthly-reports.show', $monthlyReport->fresh())
                         ->with('success', 'Monatsbericht freigegeben und eingefroren.');
    }

    // ── Discrepancy Analysis ───────────────────────────────────────────────

    public function discrepancies(Request $request): View
    {
        $request->validate([
            'year'  => ['nullable', 'integer', 'min:2020', 'max:2099'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $year         = $request->integer('year', now()->year);
        $month        = $request->integer('month', now()->month);
        $discrepancies = $this->engine->discrepanciesForMonth($year, $month);

        return view('admin.monthly_reports.discrepancies', compact('discrepancies', 'year', 'month'));
    }

    // ── Export (Excel) ─────────────────────────────────────────────────────

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $request->validate([
            'year'  => ['required', 'integer', 'min:2020', 'max:2099'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $year  = $request->integer('year');
        $month = $request->integer('month');

        $reports = $this->engine->buildReportsForMonth($year, $month);

        return $this->export->monthlyReportExcel($reports, $year, $month);
    }

    // ── Export (PDF) ───────────────────────────────────────────────────────

    public function exportPdf(MonthlyReport $monthlyReport): Response
    {
        $monthlyReport->load(['employee', 'approver']);

        return $this->export->employeeStatementPdf($monthlyReport, $monthlyReport->employee);
    }
}