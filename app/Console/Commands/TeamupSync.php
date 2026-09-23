<?php

namespace App\Console\Commands;

use App\Services\TeamupSyncService;
use Illuminate\Console\Command;

class TeamupSync extends Command
{
    protected $signature   = 'ems:teamup-sync {--limit=200 : Max pending states to push}';
    protected $description = 'Teamup: flush pending pushes and pull remote changes';

    public function handle(TeamupSyncService $sync): int
    {
        $summary = $sync->syncAll((int) $this->option('limit'));

        if (! ($summary['enabled'] ?? false)) {
            $this->info('Teamup nicht konfiguriert — nichts zu tun.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Pushed: %d | Skipped: %d | Failed: %d | Deleted: %d | Pulled: %d',
            $summary['pushed'],
            $summary['skipped'],
            $summary['failed'],
            $summary['deleted'],
            $summary['pulled'],
        ));

        foreach ($summary['errors'] as $error) {
            $this->warn($error);
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}