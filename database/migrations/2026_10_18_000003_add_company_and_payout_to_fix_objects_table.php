<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the responsible company and the amount that company receives
 * (company_payout, encrypted at rest) to a Fixobjekt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fix_objects', function (Blueprint $table) {
            $table->foreignId('company_id')
                  ->nullable()
                  ->after('location_id')
                  ->constrained('companies')
                  ->nullOnDelete();

            $table->text('company_payout')->nullable()
                  ->after('profit_margin')
                  ->comment('encrypted — amount the company receives (€)');
        });
    }

    public function down(): void
    {
        Schema::table('fix_objects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('company_payout');
        });
    }
};
