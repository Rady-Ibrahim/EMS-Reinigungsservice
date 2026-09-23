<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 'android' | 'ios' | 'web'
            $table->string('platform', 12)->default('android');
            // 'fcm' | 'webpush'
            $table->string('provider', 12)->default('fcm');
            $table->string('token', 255);
            $table->string('device_name', 191)->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['user_id', 'token']);
            $table->index(['provider', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};