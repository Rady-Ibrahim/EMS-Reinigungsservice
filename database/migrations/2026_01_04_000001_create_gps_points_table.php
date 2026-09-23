<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_points', function (Blueprint $table) {
            $table->id();

            // Each point belongs to one travel track (Anfahrt)
            $table->foreignId('travel_track_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);

            // Device-side timestamp (may differ from created_at due to offline buffering)
            $table->timestamp('recorded_at');

            // GPS accuracy in meters — useful for filtering unreliable points
            $table->float('accuracy_meters')->nullable();

            // created_at only — we never update GPS history
            $table->timestamp('created_at')->useCurrent();

            // Primary query: all points for a track in chronological order
            $table->index(['travel_track_id', 'recorded_at'], 'idx_gps_track_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_points');
    }
};
