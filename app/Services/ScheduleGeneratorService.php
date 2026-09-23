<?php

namespace App\Services;

use App\Models\FixObject;
use App\Models\FixObjectSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleGeneratorService
{
    public function __construct(
        private readonly ContractHoursCalculator $calculator
    ) {
    }

    /**
     * Generate (or skip if already exists) all schedules for a given month.
     * Called on FixObject creation and by the monthly Artisan command.
     *
     * @return int  Number of new schedules created
     */
    public function generateForMonth(FixObject $fixObject, int $year, int $month): int
    {
        if (! $fixObject->is_active) {
            return 0;
        }

        $dates   = $this->calculator->getOccurrenceDates($fixObject, $year, $month);
        $created = 0;

        DB::transaction(function () use ($fixObject, $dates, &$created) {
            foreach ($dates as $date) {
                $inserted = FixObjectSchedule::firstOrCreate(
                    [
                        'fix_object_id'  => $fixObject->id,
                        'scheduled_date' => $date->toDateString(),
                    ],
                    [
                        'scheduled_start' => $fixObject->time_start,
                        'scheduled_end'   => $fixObject->time_end,
                        'status'          => \App\Enums\ScheduleStatusEnum::Pending,
                    ]
                );

                if ($inserted->wasRecentlyCreated) {
                    $created++;
                }
            }
        });

        return $created;
    }

    /**
     * Generate schedules for a date range (used on FixObject creation).
     * Covers from today until end of next month.
     *
     * @return int  Total schedules created
     */
    public function generateInitialSchedules(FixObject $fixObject): int
    {
        $now  = Carbon::now();
        $next = $now->copy()->addMonth();

        $created  = $this->generateForMonth($fixObject, $now->year, $now->month);
        $created += $this->generateForMonth($fixObject, $next->year, $next->month);

        return $created;
    }

    /**
     * Regenerate schedules for a month after FixObject update.
     * Only generates missing ones — does NOT delete existing completed schedules.
     */
    public function regenerateForMonth(FixObject $fixObject, int $year, int $month): int
    {
        // Remove pending (not yet started) schedules before regenerating
        FixObjectSchedule::where('fix_object_id', $fixObject->id)
            ->forMonth($year, $month)
            ->where('status', \App\Enums\ScheduleStatusEnum::Pending)
            ->delete();

        return $this->generateForMonth($fixObject, $year, $month);
    }

    /**
     * Generate schedules for ALL active fix objects for a given month.
     * Called by the monthly Artisan Command.
     */
    public function generateAllForMonth(int $year, int $month): array
    {
        $results  = ['processed' => 0, 'created' => 0];
        $objects  = FixObject::active()->get();

        foreach ($objects as $fixObject) {
            $results['created']   += $this->generateForMonth($fixObject, $year, $month);
            $results['processed'] += 1;
        }

        return $results;
    }
}
