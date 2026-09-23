<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_event_assignees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('internal_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            // One attendance row per employee per event
            $table->unique(['internal_event_id', 'user_id'], 'uniq_attendee_per_event');
            $table->index('user_id', 'idx_internal_event_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_event_assignees');
    }
};
