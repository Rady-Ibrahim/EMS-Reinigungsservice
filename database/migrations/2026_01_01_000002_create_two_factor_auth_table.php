<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Placeholder table for 2FA (TOTP).
 * Full implementation (TOTP logic, QR setup, recovery flows) is Phase 7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('two_factor_auth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // TOTP secret — stored encrypted at application level
            $table->text('secret')->nullable();

            // Encrypted JSON array of one-time recovery codes
            $table->text('recovery_codes')->nullable();

            // Null = not yet confirmed/enabled
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_auth');
    }
};
