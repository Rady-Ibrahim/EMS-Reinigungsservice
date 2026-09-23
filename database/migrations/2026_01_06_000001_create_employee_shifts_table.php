<?php

use App\Enums\ShiftStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_shifts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->boolean('all_day')->default(false);
            $table->string('color', 7)->nullable();
            $table->text('notes')->nullable();

            $table->enum('status', ShiftStatusEnum::values())
                  ->default(ShiftStatusEnum::Planned->value);

            $table->timestamps();

            // Primary calendar query indexes
            $table->index(['user_id', 'start_at']);
            $table->index(['start_at', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_shifts');
    }
};
