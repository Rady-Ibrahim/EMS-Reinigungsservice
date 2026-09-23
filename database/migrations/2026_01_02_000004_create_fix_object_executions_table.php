<?php

use App\Enums\ExecutionStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fix_object_executions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('schedule_id')->constrained('fix_object_schedules')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // Actual timestamps — recorded by mobile, may differ from scheduled
            $table->datetime('actual_start');
            $table->datetime('actual_end')->nullable();

            // GPS coordinates for compliance/documentation
            $table->decimal('gps_start_lat', 10, 8)->nullable();
            $table->decimal('gps_start_lng', 11, 8)->nullable();
            $table->decimal('gps_end_lat', 10, 8)->nullable();
            $table->decimal('gps_end_lng', 11, 8)->nullable();

            // Workflow state machine
            $table->enum('status', ExecutionStatusEnum::values())
                  ->default(ExecutionStatusEnum::Started->value);

            // Photo evidence (JSON arrays of storage paths)
            $table->json('before_photos')->nullable();
            $table->json('after_photos')->nullable();

            $table->text('employee_notes')->nullable();

            // FROZEN at completion — copied from fix_objects.contract_hours
            // Never recalculated, never modifiable — immutable financial record
            $table->decimal('contract_hours_applied', 5, 2)->default(0.00);

            // Offline sync
            $table->uuid('offline_uuid')->nullable()->unique();
            $table->timestamp('client_submitted_at')->nullable();

            $table->timestamps();

            // One execution per employee per schedule
            $table->unique(['schedule_id', 'user_id'], 'uniq_execution_per_employee');
            $table->index(['user_id', 'actual_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fix_object_executions');
    }
};
