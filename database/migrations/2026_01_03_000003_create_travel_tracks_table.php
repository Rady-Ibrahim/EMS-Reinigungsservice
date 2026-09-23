<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_tracks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('extra_auftrag_id')
                  ->constrained('extra_auftraege')
                  ->cascadeOnDelete();

            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // Travel window
            $table->datetime('departure_at');
            $table->datetime('arrival_at')->nullable();

            // GPS bookends
            $table->decimal('gps_departure_lat', 10, 8)->nullable();
            $table->decimal('gps_departure_lng', 11, 8)->nullable();
            $table->decimal('gps_arrival_lat', 10, 8)->nullable();
            $table->decimal('gps_arrival_lng', 11, 8)->nullable();

            // Frozen at arrival — calculated from (arrival_at - departure_at)
            $table->unsignedInteger('travel_minutes')->nullable();

            // Copied from extra_auftraege.is_travel_time_paid at arrival time
            // Frozen to ensure historical accuracy even if order settings change later
            $table->boolean('is_paid')->default(false);

            // Offline sync
            $table->uuid('offline_uuid')->nullable()->unique();

            $table->timestamps();

            // One travel track per employee per order (rule: single trip only)
            $table->unique(['extra_auftrag_id', 'user_id'], 'uniq_travel_per_employee');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_tracks');
    }
};
