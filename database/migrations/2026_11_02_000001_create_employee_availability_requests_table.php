<?php

use App\Enums\EmployeeAvailabilityStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_availability_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('address', 255);
            $table->text('notes')->nullable();
            $table->enum('status', EmployeeAvailabilityStatusEnum::values())
                ->default(EmployeeAvailabilityStatusEnum::Pending->value);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('employee_shift_id')->nullable()->unique()
                ->constrained('employee_shifts')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'start_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_availability_requests');
    }
};
