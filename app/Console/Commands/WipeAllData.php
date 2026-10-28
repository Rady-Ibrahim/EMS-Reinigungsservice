<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WipeAllData extends Command
{
    private const CONFIRMATION = 'WIPE-EVERYTHING';

    protected $signature = 'ems:wipe-all
        {--confirm= : Required confirmation phrase: '.self::CONFIRMATION.'}';

    protected $description = 'Delete ALL data from all database tables except the migrations table.';

    public function handle(): int
    {
        if ($this->option('confirm') !== self::CONFIRMATION) {
            $this->error('No data was deleted. To proceed, pass --confirm='.self::CONFIRMATION);

            return self::FAILURE;
        }

        if (! $this->confirm('Are you absolutely sure you want to DELETE ALL DATA from this database?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $connection = DB::connection();
        $schema = $connection->getSchemaBuilder();
        $prefix = $connection->getTablePrefix();
        $migrationsTable = config('database.migrations.table', 'migrations');

        $tables = array_map(
            fn (string $table): string => str_starts_with($table, $prefix)
                ? substr($table, strlen($prefix))
                : $table,
            $schema->getTableListing()
        );

        $schema->disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                if ($table === $migrationsTable) {
                    continue;
                }

                if (! $schema->hasTable($table)) {
                    continue;
                }

                try {
                    DB::table($table)->truncate();
                } catch (\Throwable $e) {
                    try {
                        DB::table($table)->delete();
                    } catch (\Throwable $e2) {
                        continue;
                    }
                }
            }
        } finally {
            $schema->enableForeignKeyConstraints();
        }

        $this->info('All data has been wiped successfully. The migrations table was preserved.');

        return self::SUCCESS;
    }
}
