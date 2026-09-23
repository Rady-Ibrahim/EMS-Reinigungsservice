<?php

use App\Enums\ExtraExecutionStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_auftrag_executions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('extra_auftrag_id')
                  ->constrained('extra_auftraege')
                  ->cascadeOnDelete();

            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // Actual work window
            $table->datetime('work_start')->nullable();
            $table->datetime('work_end')->nullable();

            // Frozen at completion — calculated from (work_end - work_start)
            $table->unsignedInteger('work_minutes')->nullable();

            // work_minutes + (travel_minutes if is_paid) — frozen at completion
            $table->unsignedInteger('paid_minutes')->nullable();

            // GPS at work site
            $table->decimal('gps_work_start_lat', 10, 8)->nullable();
            $table->decimal('gps_work_start_lng', 11, 8)->nullable();
            $table->decimal('gps_work_end_lat', 10, 8)->nullable();
            $table->decimal('gps_work_end_lng', 11, 8)->nullable();

            $table->enum('status', ExtraExecutionStatusEnum::values())
                  ->default(ExtraExecutionStatusEnum::Travelling->value);

            // Photos — Leader responsibility only, enforced in service layer
            $table->json('before_photos')->nullable();
            $table->json('after_photos')->nullable();

            // Checklist — copied from template on execution creation, completed field updated by leader
            // [{"task": "Boden reinigen", "completed": false}, ...]
            $table->json('checklist_items')->nullable();

            $table->text('employee_notes')->nullable();

            // Offline sync
            $table->uuid('offline_uuid')->nullable()->unique();
            $table->timestamp('client_submitted_at')->nullable();

            $table->timestamps();

            // One execution per employee per order
            $table->unique(['extra_auftrag_id', 'user_id'], 'uniq_execution_per_employee');
            $table->index(['user_id', 'work_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_auftrag_executions');
    }
};
