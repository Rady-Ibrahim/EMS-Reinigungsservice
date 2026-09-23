<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fix_object_schedule_assignments', function (Blueprint $table) {
            $table->id();

            // Day-level (per-schedule) reassignment — Dynamic Assignment
            $table->foreignId('schedule_id')->constrained('fix_object_schedules')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('reason')->nullable();

            $table->timestamps();

            $table->index(['schedule_id', 'user_id']);
            $table->index(['user_id', 'schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fix_object_schedule_assignments');
    }
};
