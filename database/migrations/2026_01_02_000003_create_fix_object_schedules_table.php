<?php

use App\Enums\ScheduleStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fix_object_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fix_object_id')->constrained()->cascadeOnDelete();

            $table->date('scheduled_date');
            $table->time('scheduled_start')->nullable();
            $table->time('scheduled_end')->nullable();

            $table->enum('status', ScheduleStatusEnum::values())
                  ->default(ScheduleStatusEnum::Pending->value);

            $table->timestamps();

            // Primary calendar query index
            $table->index(['fix_object_id', 'scheduled_date']);
            $table->index(['scheduled_date', 'status']);

            // Prevent duplicate schedules for same fix_object on same date
            $table->unique(['fix_object_id', 'scheduled_date'], 'uniq_fix_schedule_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fix_object_schedules');
    }
};
