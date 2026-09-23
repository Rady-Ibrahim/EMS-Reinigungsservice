<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_appointments', function (Blueprint $table) {
            $table->id();

            // Owner — the manager/supervisor this personal calendar belongs to
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->boolean('all_day')->default(false);
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('color', 7)->nullable();
            $table->json('reminders')->nullable();

            $table->timestamps();

            // Toggle-layer query indexes
            $table->index(['user_id', 'start_at']);
            $table->index(['start_at', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_appointments');
    }
};
