<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Mandatory justification for governance events (e.g. order reopening).
            $table->text('reason')->nullable()->after('new_values');
        });

        // Extend the event enum for Phase 7 governance events.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->enum('event', [
                'created',
                'updated',
                'deleted',
                'restored',
                'reopened',
                'reassigned',
                'force_override',
                'security_changed',
                'hours_adjusted',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('reason');
            $table->enum('event', ['created', 'updated', 'deleted', 'restored'])->change();
        });
    }
};