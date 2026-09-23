<?php

namespace App\Console\Commands;

use App\Services\AdminAlertEvaluator;
use Illuminate\Console\Command;

/**
 * Evaluates the smart admin alerts.
 *
 * Options:
 *   --photos              run only the missing-photos check (hourly)
 *   --unclosed            run only the overdue-orders check (hourly)
 *   --monthly-hours       run only the monthly-hours check (daily 02:00)
 *   --all                 run everything (default for manual invocation)
 */
class AdminAlerts extends Command
{
    protected $signature = 'ems:admin-alerts
        {--photos : Missing photos check}
        {--unclosed : Overdue orders check}
        {--monthly-hours : Monthly hours coverage check}
        {--all : Run all checks}';

    protected $description = 'Evaluiert die intelligenten Admin-Alarme (Fotos, Überfälligkeit, Monatsstunden)';

    public function handle(AdminAlertEvaluator $evaluator): int
    {
        $runAll = $this->option('all')
            || (! $this->option('photos') && ! $this->option('unclosed') && ! $this->option('monthly-hours'));

        $created = 0;

        if ($runAll || $this->option('photos')) {
            $created += $evaluator->evaluateMissingPhotos();
        }
        if ($runAll || $this->option('unclosed')) {
            $created += $evaluator->evaluateUnclosed();
        }
        if ($runAll || $this->option('monthly-hours')) {
            $created += $evaluator->evaluateMonthlyHours();
        }

        $this->info("Admin-Alarme ausgewertet — {$created} neu(e) erstellt.");

        return self::SUCCESS;
    }
}