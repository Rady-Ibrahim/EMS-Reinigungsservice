<?php

use App\Enums\InternalEventTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('title');
            $table->enum('event_type', InternalEventTypeEnum::values());
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->boolean('all_day')->default(false);
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable();
            $table->json('reminders')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Primary calendar query indexes
            $table->index(['event_type', 'start_at']);
            $table->index(['start_at', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_events');
    }
};
