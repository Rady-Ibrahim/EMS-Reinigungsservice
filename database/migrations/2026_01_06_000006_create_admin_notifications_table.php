<?php

use App\Enums\AdminNotificationTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();

            $table->enum('type', AdminNotificationTypeEnum::values());
            $table->string('title');
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('is_read')->default(false);

            // created_at only — notifications are immutable inbox entries
            $table->timestamp('created_at')->nullable();

            $table->index(['is_read', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
