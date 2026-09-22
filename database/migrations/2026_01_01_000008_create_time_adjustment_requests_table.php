<?php

use App\Enums\AdjustmentStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_adjustment_requests', function (Blueprint $table) {
            $table->id();

            // Requesting employee
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();

            // Polymorphic-style reference (fix_object or extra_auftrag — set in later phases)
            $table->enum('job_type', ['fix_object', 'extra_auftrag']);
            $table->unsignedBigInteger('job_id');
            $table->index(['job_type', 'job_id']);

            // Requested new times
            $table->datetime('requested_start');
            $table->datetime('requested_end');

            // Original times — preserved for audit trail, never overwritten
            $table->datetime('original_start')->nullable();
            $table->datetime('original_end')->nullable();

            $table->text('reason');

            $table->enum('status', AdjustmentStatusEnum::values())
                  ->default(AdjustmentStatusEnum::Pending->value);

            // Admin review
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_note')->nullable();

            // Offline sync support
            $table->uuid('offline_uuid')->nullable()->unique();
            $table->timestamp('client_submitted_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_adjustment_requests');
    }
};
