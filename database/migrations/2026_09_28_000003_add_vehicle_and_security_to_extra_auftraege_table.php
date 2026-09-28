<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field vehicle and equipment used on an order, plus the site-security state
 * the mobile app renders on the closing screen.
 *
 * The task detail endpoint (GET /api/v1/extra-orders/{id}) returns these as
 * `vehicle_info` / `equipment_info` so the app can show "which van, which
 * machine" while the employee is out on site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extra_auftraege', function (Blueprint $table) {
            $table->string('vehicle_label', 120)->nullable()
                  ->comment('Free text, e.g. "Sprinter · BM-XY 1234"');
            $table->json('equipment_info')->nullable()
                  ->comment('[{"name":"Staubsauger","asset_id":"..."}]');

            // Site security state at the order level (execution level holds
            // the per-employee hand-over confirmations)
            $table->boolean('has_alarm_system')->default(false)
                  ->comment('Whether the site has an alarm that must be armed');
            $table->boolean('has_key_safe')->default(false)
                  ->comment('Whether a key/card safe is used on site');
        });
    }

    public function down(): void
    {
        Schema::table('extra_auftraege', function (Blueprint $table) {
            $table->dropColumn(['vehicle_label', 'equipment_info', 'has_alarm_system', 'has_key_safe']);
        });
    }
};
