<?php

use App\Enums\FileCategoryEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `title` column required by the LocationDocument contract.
 *
 * Existing rows are backfilled from `original_name` so no document is left
 * without a human readable title.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_files', function (Blueprint $table) {
            $table->string('title')->nullable()->after('location_id');
        });

        DB::table('location_files')
            ->whereNull('title')
            ->orWhere('title', '')
            ->update([
                'title' => DB::raw("COALESCE(NULLIF(original_name, ''), 'Dokument')"),
            ]);

        // Existing rows also lack a category — align them with the new default.
        DB::table('location_files')
            ->whereNull('category')
            ->update(['category' => FileCategoryEnum::Other->value]);
    }

    public function down(): void
    {
        Schema::table('location_files', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
