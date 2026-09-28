<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refused close attempts on Extra-Aufträge.
 *
 * Stored in its own table rather than in `admin_notifications`: an attempt is
 * an operational trace, not something the administrators need to see in their
 * inbox. `AdminAlertEvaluator` reads this to raise one deduped
 * `checklist_blocked_close` alert per order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_close_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('extra_auftrag_id')->constrained('extra_auftraege')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('reasons');
            $table->string('dedupe_key')->unique();
            $table->timestamps();

            $table->index(['extra_auftrag_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_close_attempts');
    }
};
