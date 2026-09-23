<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teamup_settings', function (Blueprint $table) {
            $table->id();

            $table->string('calendar_key')->nullable();   // encrypted at rest
            $table->string('api_key')->nullable();        // encrypted at rest
            $table->string('timezone')->default('Europe/Berlin');
            // Map: {"default": 12345, "personal_appointment": 67890, ...}
            $table->json('subcalendar_ids')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teamup_settings');
    }
};
