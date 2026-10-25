<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fix_object_schedules', function (Blueprint $table) {
            $table->date('source_date')->nullable()->after('scheduled_date');
            $table->boolean('is_manually_adjusted')->default(false)->after('status');
            $table->index(['fix_object_id', 'source_date']);
        });

        DB::table('fix_object_schedules')
            ->whereNull('source_date')
            ->update(['source_date' => DB::raw('scheduled_date')]);
    }

    public function down(): void
    {
        Schema::table('fix_object_schedules', function (Blueprint $table) {
            $table->dropIndex(['fix_object_id', 'source_date']);
            $table->dropColumn(['source_date', 'is_manually_adjusted']);
        });
    }
};
