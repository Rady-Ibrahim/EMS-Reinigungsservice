<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\AssigneeRoleEnum;
use App\Enums\ExtraAuftragStatusEnum;
use App\Enums\RoleEnum;
use App\Enums\ScheduleStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ExtraAuftrag;
use App\Models\FixObject;
use App\Models\FixObjectExecution;
use App\Models\FixObjectSchedule;
use App\Models\MonthlyReport;
use App\Models\TimeAdjustmentRequest;
use App\Models\User;
use App\Services\TimeTrackingEngine;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = CarbonImmutable::today();
        $monthStart = $today->startOfMonth();
        $monthEnd = $today->endOfMonth();
        $employeeRoles = [RoleEnum::Vorarbeiter, RoleEnum::Mitarbeiter];

        $fixSchedules = FixObjectSchedule::query()
            ->whereDate('scheduled_date', $today->toDateString())
            ->where('status', '!=', ScheduleStatusEnum::Skipped->value)
            ->with([
                'fixObject.customer',
                'fixObject.location',
                'fixObject.assignments.user',
                'scheduleAssignments.user',
                'executions.employee',
            ])
            ->get();

        $extraOrders = ExtraAuftrag::query()
            ->whereDate('scheduled_date', $today->toDateString())
            ->where('status', '!=', ExtraAuftragStatusEnum::Cancelled->value)
            ->with(['customer', 'location', 'assignees.user', 'executions.employee'])
            ->get();

        $todayJobs = $fixSchedules->map(fn (FixObjectSchedule $schedule) => $this->fixJobRow($schedule))
            ->concat($extraOrders->map(fn (ExtraAuftrag $order) => $this->extraJobRow($order)))
            ->sortBy('sort_time')
            ->values();

        $activeCustomers = Customer::active()->count();
        $activeEmployees = User::query()
            ->where('is_active', true)
            ->whereIn('role', $employeeRoles)
            ->count();
        $activeFixObjects = FixObject::query()->where('is_active', true)->count();
        $monthHours = MonthlyReport::query()
            ->where('year', $today->year)
            ->where('month', $today->month)
            ->sum('total_paid_hours');

        $isAdministrator = auth()->user()?->isAdministrator() ?? false;
        $financialOverview = $isAdministrator
            ? $this->financialOverview($monthStart, $monthEnd)
            : null;

        return view('admin.dashboard', [
            'todayJobs' => $todayJobs,
            'todayJobCount' => $todayJobs->count(),
            'pendingAdjustments' => TimeAdjustmentRequest::pending()->count(),
            'activeCustomers' => $activeCustomers,
            'activeEmployees' => $activeEmployees,
            'activeFixObjects' => $activeFixObjects,
            'monthHours' => $monthHours,
            'isAdministrator' => $isAdministrator,
            'financialOverview' => $financialOverview,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixJobRow(FixObjectSchedule $schedule): array
    {
        $fixObject = $schedule->fixObject;
        $location = $fixObject?->location;
        $employees = collect();

        $overrides = $schedule->scheduleAssignments->sortByDesc('id');
        if ($overrides->isNotEmpty()) {
            $employees = collect([$overrides->first()->user])->filter();
        } elseif ($fixObject) {
            $date = \Illuminate\Support\Carbon::parse($schedule->scheduled_date);
            $employees = $fixObject->assignments
                ->filter(fn ($assignment) => $assignment->isActiveOn($date))
                ->map(fn ($assignment) => $assignment->user)
                ->filter()
                ->unique('id')
                ->values();
        }

        return [
            'sort_time' => $schedule->scheduled_start ?: '00:00:00',
            'start' => $schedule->scheduled_start,
            'end' => $schedule->scheduled_end,
            'title' => $fixObject?->title ?? '—',
            'customer' => $fixObject?->customer?->name,
            'location' => $location,
            'location_name' => $location?->name,
            'address' => $location?->fullAddress(),
            'coordinates' => $this->coordinates($location),
            'team' => $employees->map(fn (User $employee) => [
                'name' => $employee->name,
                'role' => $employee->role,
                'is_leader' => $employee->role === RoleEnum::Vorarbeiter,
            ])->values(),
            'status' => $schedule->status->label(),
            'photo_count' => $schedule->executions->sum(
                fn ($execution) => count($execution->before_photos ?? []) + count($execution->after_photos ?? [])
            ),
            'checklist' => null,
            'has_key_safe' => false,
            'has_alarm_code' => filled($location?->security_code),
            'url' => $fixObject ? route('admin.fix-objects.show', $fixObject) : route('admin.calendar.index'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extraJobRow(ExtraAuftrag $order): array
    {
        $location = $order->location;
        $leaderId = $order->assignees
            ->first(fn ($assignee) => $assignee->role_in_order === AssigneeRoleEnum::Leader)
            ?->user_id;
        $checklistExecution = $order->executions->firstWhere('user_id', $leaderId)
            ?? $order->executions->first();
        $checklistItems = collect($checklistExecution?->checklist_items ?? $order->checklist_template ?? []);

        return [
            'sort_time' => $order->scheduled_time_start ?: '00:00:00',
            'start' => $order->scheduled_time_start,
            'end' => null,
            'title' => $order->title,
            'customer' => $order->customer?->name,
            'location' => $location,
            'location_name' => $location?->name,
            'address' => $location?->fullAddress(),
            'coordinates' => $this->coordinates($location),
            'team' => $order->assignees->map(fn ($assignee) => [
                'name' => $assignee->user?->name ?? '—',
                'role' => $assignee->user?->role,
                'is_leader' => $assignee->role_in_order === AssigneeRoleEnum::Leader,
            ])->values(),
            'status' => $order->status->label(),
            'photo_count' => $order->executions->sum(
                fn ($execution) => count($execution->before_photos ?? []) + count($execution->after_photos ?? [])
            ),
            'checklist' => [
                'done' => $checklistItems->filter(fn ($item) => (bool) ($item['completed'] ?? false))->count(),
                'total' => $checklistItems->count(),
            ],
            'has_key_safe' => (bool) $order->has_key_safe,
            'has_alarm_code' => (bool) $order->has_alarm_system || filled($location?->security_code),
            'url' => route('admin.extra-auftraege.show', $order),
        ];
    }

    private function coordinates(?\App\Models\CustomerLocation $location): ?string
    {
        if ($location?->latitude === null || $location->longitude === null) {
            return null;
        }

        return $location->latitude.', '.$location->longitude;
    }

    /**
     * @return array<string, mixed>
     */
    private function financialOverview(CarbonImmutable $monthStart, CarbonImmutable $monthEnd): array
    {
        $fixContracts = FixObject::query()
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $monthEnd->toDateString())
            ->where(fn ($query) => $query->whereNull('valid_until')
                ->orWhereDate('valid_until', '>=', $monthStart->toDateString()))
            ->with('company')
            ->get();

        $monthOrders = ExtraAuftrag::query()
            ->whereBetween('scheduled_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->where('status', '!=', ExtraAuftragStatusEnum::Cancelled->value)
            ->with('company')
            ->get();

        $revenue = $fixContracts->sum(fn (FixObject $contract) => (float) ($contract->price_per_month ?? 0))
            + $monthOrders->sum(fn (ExtraAuftrag $order) => (float) ($order->price ?? 0));
        $companyPayout = $fixContracts->sum(fn (FixObject $contract) => $contract->company_id
            ? (float) ($contract->company_payout ?? 0)
            : 0)
            + $monthOrders->sum(fn (ExtraAuftrag $order) => $order->company_id
                ? (float) ($order->company_payout ?? 0)
                : 0);

        $timeTracking = app(TimeTrackingEngine::class);
        $laborCost = 0.0;

        FixObjectExecution::query()
            ->whereNotNull('actual_end')
            ->whereBetween('actual_start', [$monthStart->startOfDay(), $monthEnd->endOfDay()])
            ->with('employee.employeeProfile')
            ->get()
            ->each(function (FixObjectExecution $execution) use (&$laborCost, $timeTracking): void {
                $hourlyCost = $execution->employee?->employeeProfile?->getHourlyCostAsFloat() ?? 0.0;
                $laborCost += $timeTracking->summarizeFixExecution($execution)->paidHours * $hourlyCost;
            });

        \App\Models\ExtraAuftragExecution::query()
            ->whereNotNull('work_end')
            ->whereBetween('work_start', [$monthStart->startOfDay(), $monthEnd->endOfDay()])
            ->with('employee.employeeProfile')
            ->get()
            ->each(function ($execution) use (&$laborCost, $timeTracking): void {
                $hourlyCost = $execution->employee?->employeeProfile?->getHourlyCostAsFloat() ?? 0.0;
                $laborCost += $timeTracking->summarizeExtraExecution($execution)->paidHours * $hourlyCost;
            });

        $companies = Company::query()
            ->withCount(['employees' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('name')
            ->get()
            ->map(function (Company $company) use ($fixContracts, $monthOrders): array {
                $payout = $fixContracts->where('company_id', $company->id)
                    ->sum(fn (FixObject $contract) => (float) ($contract->company_payout ?? 0))
                    + $monthOrders->where('company_id', $company->id)
                        ->sum(fn (ExtraAuftrag $order) => (float) ($order->company_payout ?? 0));

                return [
                    'name' => $company->name,
                    'active_employees' => $company->employees_count,
                    'payout' => $payout,
                ];
            })
            ->filter(fn (array $company) => $company['active_employees'] > 0 || $company['payout'] > 0)
            ->values();

        return [
            'revenue' => round($revenue, 2),
            'company_payout' => round($companyPayout, 2),
            'labor_cost' => round($laborCost, 2),
            'profit' => round($revenue - $companyPayout - $laborCost, 2),
            'active_company_employees' => User::query()
                ->where('is_active', true)
                ->whereIn('role', [RoleEnum::Vorarbeiter, RoleEnum::Mitarbeiter])
                ->whereNotNull('company_id')
                ->count(),
            'companies' => $companies,
        ];
    }
}
