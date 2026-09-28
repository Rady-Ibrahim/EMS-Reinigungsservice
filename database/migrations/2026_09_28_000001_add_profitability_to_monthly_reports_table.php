<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persists the profitability figures produced by MonthlyReportEngineService.
 *
 * The hours columns live next to them, so the money is recomputed on every
 * live rebuild of an unapproved report and then frozen by the approval
 * snapshot exactly like the hours are.
 *
 * `hourly_cost_applied` stores the employer hourly cost the report was
 * calculated with, so a later rate change cannot silently restate a settled
 * period — the frozen numbers stay explainable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_reports', function (Blueprint $table) {
            $table->decimal('revenue', 12, 2)->default(0)->after('total_paid_hours');
            $table->decimal('employee_cost', 12, 2)->default(0)->after('revenue');
            $table->decimal('profit', 12, 2)->default(0)->after('employee_cost');
            $table->decimal('hourly_cost_applied', 10, 2)->nullable()->after('profit');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_reports', function (Blueprint $table) {
            $table->dropColumn(['revenue', 'employee_cost', 'profit', 'hourly_cost_applied']);
        });
    }
};
