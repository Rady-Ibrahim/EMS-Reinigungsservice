<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SeedData extends Command
{
    protected $signature = 'ems:seed';

    protected $description = 'Run the database seeders (DatabaseSeeder).';

    public function handle(): int
    {
        $this->info('Running database seeders...');

        $exitCode = Artisan::call('db:seed', [], $this->getOutput());

        if ($exitCode === 0) {
            $this->info('Database seeding completed.');
            return self::SUCCESS;
        }

        $this->error('Database seeding failed.');
        return self::FAILURE;
    }
}
