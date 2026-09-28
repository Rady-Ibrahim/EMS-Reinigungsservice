<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site-security hand-over for the closing screen (Auftrag beenden & Übergabe).
 *
 * The mobile app asks the employee to confirm the three physical hand-over
 * steps before leaving and optionally adds a shift note. These are recorded per
 * execution because each team member signs off their own departure.
 *
 * `pause_*` supports the unpaid break (Pause) — the clock keeps running in the
 * background, but the paused span is stored so it can be deducted from the
 * paid minutes at close.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extra_auftrag_executions', function (Blueprint $table) {
            // Short hand-over note from the closing screen. `employee_notes`
            // is the long free-text log; this is the ≤200 char field note.
            $table->string('shift_note', 200)->nullable();

            // Security hand-over confirmations
            $table->boolean('key_returned')->default(false)
                  ->comment('Key/card returned to the safe');
            $table->boolean('windows_closed')->default(false)
                  ->comment('Windows and doors closed');
            $table->boolean('alarm_activated')->default(false)
                  ->comment('Alarm system switched on');

            // Unpaid break
            $table->timestamp('pause_started_at')->nullable();
            $table->timestamp('pause_ended_at')->nullable();
            $table->unsignedInteger('pause_minutes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('extra_auftrag_executions', function (Blueprint $table) {
            $table->dropColumn([
                'shift_note', 'key_returned', 'windows_closed', 'alarm_activated',
                'pause_started_at', 'pause_ended_at', 'pause_minutes',
            ]);
        });
    }
};
