<?php

namespace App\Services;

use App\Models\FixObject;
use App\Models\FixObjectSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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
                $sourceDate = $date->toDateString();
                $alreadyScheduled = FixObjectSchedule::query()
                    ->where('fix_object_id', $fixObject->id)
                    ->where(fn ($query) => $query
                        ->whereDate('source_date', $sourceDate)
                        ->orWhereDate('scheduled_date', $sourceDate))
                    ->exists();

                if ($alreadyScheduled) {
                    continue;
                }

                $inserted = FixObjectSchedule::firstOrCreate(
                    [
                        'fix_object_id'  => $fixObject->id,
                        'scheduled_date' => $sourceDate,
                    ],
                    [
                        'source_date' => $sourceDate,
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
     * Ensure recurring schedules exist for every month touched by a calendar
     * feed request. Generation is idempotent, so saved execution states remain
     * untouched while missing occurrences become visible in the calendar.
     */
    public function generateForRange(CarbonInterface $from, CarbonInterface $to): int
    {
        $created = 0;
        $month = Carbon::instance($from)->startOfMonth();
        $lastMonth = Carbon::instance($to)->startOfMonth();
        $objects = FixObject::active()->get();

        while ($month->lte($lastMonth)) {
            foreach ($objects as $fixObject) {
                $created += $this->generateForMonth($fixObject, $month->year, $month->month);
            }

            $month->addMonth();
        }

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
            ->where('is_manually_adjusted', false)
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
