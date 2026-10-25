<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_event_marks', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 32);
            $table->unsignedBigInteger('event_id');
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_type', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_marks');
    }
};
