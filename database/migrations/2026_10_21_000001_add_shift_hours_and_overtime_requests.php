<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->decimal('work_hours', 5, 2)->nullable()->after('end_at');
            $table->decimal('approved_extra_hours', 5, 2)->default(0)->after('work_hours');
        });

        Schema::table('time_adjustment_requests', function (Blueprint $table) {
            $table->string('job_type')->change();
            $table->decimal('requested_extra_hours', 5, 2)->nullable()->after('requested_end');
        });

        Schema::table('monthly_reports', function (Blueprint $table) {
            $table->decimal('shift_paid_hours', 8, 2)->default(0)->after('extra_actual_hours');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_reports', function (Blueprint $table) {
            $table->dropColumn('shift_paid_hours');
        });

        Schema::table('time_adjustment_requests', function (Blueprint $table) {
            $table->dropColumn('requested_extra_hours');
        });

        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->dropColumn(['work_hours', 'approved_extra_hours']);
        });
    }
};
