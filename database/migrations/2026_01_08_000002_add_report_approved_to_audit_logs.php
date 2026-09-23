<?php

use App\Enums\AuditEventEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add the report_approved event to existing databases.
        // Fresh installations already pick it up via AuditEventEnum::values()
        // in the original create migration.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->enum('event', AuditEventEnum::values())->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->enum('event', array_values(array_diff(AuditEventEnum::values(), ['report_approved'])))->change();
        });
    }
};