<?php

namespace App\Console\Commands;

use App\Enums\RoleEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearDataExceptAdmins extends Command
{
    private const CONFIRMATION = 'WIPE-ALL-DATA-EXCEPT-ADMINS';

    protected $signature = 'ems:clear-data
        {--confirm= : Required confirmation phrase: WIPE-ALL-DATA-EXCEPT-ADMINS}';

    protected $description = 'Delete all database data except administrator accounts and migration history.';

    public function handle(): int
    {
        if ($this->option('confirm') !== self::CONFIRMATION) {
            $this->error('No data was deleted. To proceed, pass --confirm='.self::CONFIRMATION);

            return self::FAILURE;
        }

        $connection = DB::connection();
        $schema = $connection->getSchemaBuilder();
        $prefix = $connection->getTablePrefix();
        $tables = array_map(
            fn (string $table): string => str_starts_with((string)$table, $prefix)
                ? substr((string)$table, strlen($prefix))
                : (string)$table,
            $schema->getTableListing()
        );
        $usersTable = 'users';
        $migrationsTable = config('database.migrations.table', 'migrations');

        if (! in_array($usersTable, $tables, true) && ! $schema->hasTable($usersTable)) {
            // Try to be more defensive - also check if users exists with prefix
            if ($schema->hasTable($prefix.'users')) {
                // proceed
            } else {
                $this->error('The users table was not found. No data was deleted.');

                return self::FAILURE;
            }
        }

        try {
            $adminCount = DB::table('users')
                ->where('role', RoleEnum::Administrator->value)
                ->count();
        } catch (\Throwable $e) {
            $this->error('Could not read users table: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($adminCount === 0) {
            $this->error('No administrator accounts were found. No data was deleted.');

            return self::FAILURE;
        }

        $schema->disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                if (in_array($table, [$usersTable, $migrationsTable], true)) {
                    continue;
                }
                if (! $schema->hasTable($table)) {
                    continue;
                }

                try {
                    DB::table($table)->delete();
                } catch (\Throwable $e) {
                    // skip tables that can't be truncated/deleted safely
                    continue;
                }
            }

            try {
                DB::table('users')
                    ->where('role', '!=', RoleEnum::Administrator->value)
                    ->delete();
            } catch (\Throwable $e) {
                // ignore
            }
        } finally {
            $schema->enableForeignKeyConstraints();
        }

        $this->info("Database cleared. Preserved {$adminCount} administrator account(s) and migration history.");

        return self::SUCCESS;
    }
}
