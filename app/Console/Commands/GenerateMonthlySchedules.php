<?php

namespace App\Console\Commands;

use App\Services\ScheduleGeneratorService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlySchedules extends Command
{
    protected $signature = 'ems:generate-schedules
                            {--year=    : Year (default: next month\'s year)}
                            {--month=   : Month 1-12 (default: next month)}';

    protected $description = 'Pre-generate Fixobjekt schedules for a given month (defaults to next month).';

    public function handle(ScheduleGeneratorService $generator): int
    {
        $next  = Carbon::now()->addMonth();
        $year  = (int) ($this->option('year')  ?: $next->year);
        $month = (int) ($this->option('month') ?: $next->month);

        if ($month < 1 || $month > 12) {
            $this->error('Month must be between 1 and 12.');
            return Command::FAILURE;
        }

        $this->info("Generating schedules for {$year}-{$month}...");

        $results = $generator->generateAllForMonth($year, $month);

        $this->info("✓ Processed: {$results['processed']} fix objects");
        $this->info("✓ Created:   {$results['created']} new schedules");

        return Command::SUCCESS;
    }
}
