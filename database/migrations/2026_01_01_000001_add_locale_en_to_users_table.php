<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MySQL ENUM alteration — adds 'en' to the locale column.
 * We use raw SQL for MySQL ENUM modification since Blueprint::change()
 * does not reliably modify ENUM definitions across all driver versions.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE users MODIFY COLUMN locale ENUM('de', 'ar', 'en') NOT NULL DEFAULT 'de'"
        );
    }

    public function down(): void
    {
        // Revert any 'en' values before shrinking the enum
        DB::statement("UPDATE users SET locale = 'de' WHERE locale = 'en'");
        DB::statement(
            "ALTER TABLE users MODIFY COLUMN locale ENUM('de', 'ar') NOT NULL DEFAULT 'de'"
        );
    }
};
