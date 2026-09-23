<?php

namespace App\Services;

use App\Enums\AuditEventEnum;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragExecution;
use App\Models\FixObject;
use App\Models\FixObjectExecution;
use App\Models\FixObjectSchedule;
use App\Models\MonthlyReport;
use App\Models\User;
use App\ValueObjects\TimeTrackingSummary;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Central monthly report engine.
 *
 * Aggregates every employee's monthly hours into a report that can be
 * reviewed by the admin and then approved/frozen:
 *
 *  - Fixobjekte   → paid hours = contract_hours_applied (frozen at start)
 *  - Extra-Aufträge → paid hours = work + (travel if is_travel_time_paid)
 *
 * Until approval the report is regenerated from live data; approving copies
 * a full snapshot and stamps approved_at / approved_by so no retroactive
 * change can silently alter a settled payroll period.
 */
class MonthlyReportEngineService
{
    public function __construct(
        private readonly TimeTrackingEngine $timeTracking,
        private readonly ContractHoursCalculator $contractHours,
        private readonly AuditLogger $audit
    ) {
    }

    // ── Public API ─────────────────────────────────────────────────────────

    /**
     * Build (or refresh) the live report for one employee/month.
     * Never mutates an already-approved report.
     */
    public function buildReport(User $employee, int $year, int $month): MonthlyReport
    {
        $report = MonthlyReport::firstOrNew(['user_id' => $employee->id, 'year' => $year, 'month' => $month]);

        if ($report->isApproved()) {
            return $report;
        }

        $aggregate = $this->aggregateFor($employee, $year, $month);

        $report->fill([
            'fix_paid_hours'      => $aggregate['fix_paid_hours'],
            'fix_actual_hours'    => $aggregate['fix_actual_hours'],
            'extra_work_hours'    => $aggregate['extra_work_hours'],
            'extra_travel_hours'  => $aggregate['extra_travel_hours'],
            'extra_paid_hours'    => $aggregate['extra_paid_hours'],
            'extra_actual_hours'  => $aggregate['extra_actual_hours'],
            'total_paid_hours'    => $aggregate['total_paid_hours'],
        ])->save();

        return $report->fresh();
    }

    /**
     * Approve + freeze a report. Throws if already approved.
     * Writes a governance audit entry (event = report_approved).
     */
    public function approve(User $employee, int $year, int $month, User $admin): MonthlyReport
    {
        return DB::transaction(function () use ($employee, $year, $month, $admin) {
            $report = MonthlyReport::lockForUpdate()
                ->where('user_id', $employee->id)
                ->where('year', $year)
                ->where('month', $month)
                ->firstOr(function () use ($employee, $year, $month) {
                    return $this->buildReport($employee, $year, $month);
                });

            if ($report->isApproved()) {
                throw new \RuntimeException('Der Monatsbericht ist bereits freigegeben.');
            }

            $lineItems = $this->lineItemsFor($employee, $year, $month);

            $report->update([
                'snapshot'      => [
                    'aggregate' => $this->aggregateFor($employee, $year, $month),
                    'line_items' => $lineItems->values(),
                    'generated_at' => now()->toIso8601String(),
                ],
                'approved_at'   => now(),
                'approved_by'   => $admin->id,
            ]);

            $this->audit->record($employee, AuditEventEnum::ReportApproved,
                oldValues: ['approved' => false],
                newValues: [
                    'approved' => true,
                    'period'   => "{$year}-{$month}",
                    'total_paid_hours' => (float) $report->total_paid_hours,
                ],
                reason: 'Monatsbericht freigegeben und eingefroren',
                actorId: $admin->id
            );

            return $report->fresh(['employee', 'approver']);
        });
    }

    /**
     * Per-execution detail rows for the report (both job types).
     * Fix: contract_hours_applied · Extra: work + paid travel.
     *
     * @return Collection<int, array{...}>
     */
    public function lineItemsFor(User $employee, int $year, int $month): Collection
    {
        $items = collect();

        FixObjectExecution::where('user_id', $employee->id)
            ->whereNotNull('actual_end')
            ->whereYear('actual_start', $year)
            ->whereMonth('actual_start', $month)
            ->with(['schedule.fixObject.customer', 'schedule.fixObject.location'])
            ->get()
            ->each(function (FixObjectExecution $exec) use ($items) {
                $items->push($this->fixItem($exec));
            });

        ExtraAuftragExecution::where('user_id', $employee->id)
            ->whereNotNull('work_end')
            ->whereYear('work_start', $year)
            ->whereMonth('work_start', $month)
            ->with(['extraAuftrag.customer', 'extraAuftrag.location'])
            ->get()
            ->each(function (ExtraAuftragExecution $exec) use ($items) {
                $items->push($this->extraItem($exec));
            });

        return $items->sortBy('date')->values();
    }

    /**
     * Aggregate monthly hours for one employee.
     *
     * @return array{fix_paid_hours: float, fix_actual_hours: float, extra_work_hours: float, extra_travel_hours: float, extra_paid_hours: float, extra_actual_hours: float, total_paid_hours: float, fix_count: int, extra_count: int}
     */
    public function aggregateFor(User $employee, int $year, int $month): array
    {
        $totals = $this->timeTracking->monthlyTotalsForEmployee($employee, $year, $month);

        // Travel hours are only tracked for extras.
        $extraTravelHours = 0.0;

        ExtraAuftragExecution::where('user_id', $employee->id)
            ->whereNotNull('work_end')
            ->whereYear('work_start', $year)
            ->whereMonth('work_start', $month)
            ->get()
            ->each(function (ExtraAuftragExecution $exec) use (&$extraTravelHours) {
                $extraTravelHours += $this->timeTracking->summarizeExtraExecution($exec)->travelHours;
            });

        return [
            'fix_paid_hours'     => $totals['fix_paid_hours'],
            'fix_actual_hours'   => $totals['fix_actual_hours'],
            'extra_work_hours'   => $totals['extra_actual_hours'],
            'extra_travel_hours' => round($extraTravelHours, 2),
            'extra_paid_hours'   => $totals['extra_paid_hours'],
            'extra_actual_hours' => $totals['extra_actual_hours'],
            'total_paid_hours'   => $totals['total_paid_hours'],
            'fix_count'          => $totals['fix_count'],
            'extra_count'        => $totals['extra_count'],
        ];
    }

    /**
     * Admin discrepancy analysis for a whole month:
     * per employee, planned/contract vs paid vs actual field hours.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function discrepanciesForMonth(int $year, int $month): Collection
    {
        $employees = User::whereIn('role', ['vorarbeiter', 'mitarbeiter'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $monthStart = Carbon::create($year, $month, 1);
        $monthEnd   = $monthStart->copy()->endOfMonth();

        return $employees->map(function (User $employee) use ($year, $month, $monthStart, $monthEnd) {
            $aggregate    = $this->aggregateFor($employee, $year, $month);
            $planned      = $this->plannedMonthlyHours($employee, $year, $month, $monthStart, $monthEnd);

            return [
                'employee_id'   => $employee->id,
                'employee_name' => $employee->name,
                'planned_hours' => $planned,
                'paid_hours'    => $aggregate['total_paid_hours'],
                'actual_hours'  => round($aggregate['fix_actual_hours'] + $aggregate['extra_actual_hours'], 2),
                'balance'       => round($aggregate['total_paid_hours'] - $planned, 2),
            ];
        })->filter(fn(array $row) => $row['paid_hours'] > 0 || $row['planned_hours'] > 0)->values();
    }

    /**
     * Build the report for every active employee for a month (used by Excel).
     *
     * @return Collection<int, MonthlyReport>
     */
    public function buildReportsForMonth(int $year, int $month): Collection
    {
        return User::whereIn('role', ['vorarbeiter', 'mitarbeiter'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn(User $employee) => $this->buildReport($employee, $year, $month))
            ->values();
    }

    // ── Admin helper ───────────────────────────────────────────────────────

    public function employeesWithReports(int $year, int $month): Collection
    {
        return User::whereIn('role', ['vorarbeiter', 'mitarbeiter'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn(User $employee) => $this->buildReport($employee, $year, $month));
    }

    // ── Private ────────────────────────────────────────────────────────────

    /**
     * @return array{job_type: string, job_id: int, date: string, label: string, customer: string, actual_hours: float, paid_hours: float, travel_hours: float, contract_hours: float, travel_is_paid: bool}
     */
    private function fixItem(FixObjectExecution $exec): array
    {
        /** @var TimeTrackingSummary $summary */
        $summary = $this->timeTracking->summarizeFixExecution($exec);

        /** @var FixObjectSchedule|null $schedule */
        $schedule = $exec->schedule;
        $fix      = $schedule?->fixObject;

        return array_merge($summary->toArray(), [
            'label'    => $fix?->title ?? "Fixobjekt #{$exec->schedule_id}",
            'customer' => $fix?->customer?->name ?? '—',
        ]);
    }

    /**
     * @return array{job_type: string, job_id: int, date: string, label: string, customer: string, actual_hours: float, paid_hours: float, travel_hours: float, contract_hours: float, travel_is_paid: bool}
     */
    private function extraItem(ExtraAuftragExecution $exec): array
    {
        /** @var TimeTrackingSummary $summary */
        $summary = $this->timeTracking->summarizeExtraExecution($exec);

        /** @var ExtraAuftrag|null $order */
        $order = $exec->extraAuftrag;

        return array_merge($summary->toArray(), [
            'label'        => $order?->title ?? "Extra-Auftrag #{$exec->extra_auftrag_id}",
            'customer'     => $order?->customer?->name ?? '—',
            'job_id'       => (int) $exec->extra_auftrag_id,
        ]);
    }

    private function plannedMonthlyHours(User $employee, int $year, int $month, Carbon $monthStart, Carbon $monthEnd): float
    {
        $planned = 0.0;

        $assignments = \App\Models\FixObjectAssignment::forEmployee($employee->id)
            ->with('fixObject')
            ->get();

        foreach ($assignments as $assignment) {
            if ($assignment->isActiveOn($monthStart) || $assignment->isActiveOn($monthEnd)) {
                $planned += $this->contractHours->monthlyHours($assignment->fixObject, $year, $month);
            }
        }

        return round($planned, 2);
    }
}