<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // Recipient — every notification belongs to exactly one user.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Free-form type key from App\Enums\NotificationTypeEnum (kept as
            // string to avoid enum ALTERs when new kinds of events appear).
            $table->string('type', 60)->index();

            $table->string('title');
            $table->text('message')->nullable();
            $table->json('payload')->nullable();

            // Read state — read_at is the only mutable field.
            $table->timestamp('read_at')->nullable();

            // created_at only — in-app notifications are push-style entries.
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};