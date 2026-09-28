<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `hourly_cost` — the internal, per-hour cost of employing a person.
 *
 * This is the employer's cost, NOT the rate charged to the customer
 * (`hourly_rate`), and it is what the profitability calculation multiplies the
 * paid hours by. Encrypted at rest for the same reason as the rate: it is
 * commercially sensitive payroll data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->text('hourly_cost')->nullable()->after('hourly_rate')->comment('Encrypted internal hourly cost (employer cost)');
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn('hourly_cost');
        });
    }
};
