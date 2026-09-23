<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');

            // Aggregated, engine-computed hours (regenerated until approval).
            $table->decimal('fix_paid_hours', 8, 2)->default(0);
            $table->decimal('fix_actual_hours', 8, 2)->default(0);
            $table->decimal('extra_work_hours', 8, 2)->default(0);
            $table->decimal('extra_travel_hours', 8, 2)->default(0);
            $table->decimal('extra_paid_hours', 8, 2)->default(0);
            $table->decimal('extra_actual_hours', 8, 2)->default(0);
            $table->decimal('total_paid_hours', 8, 2)->default(0);

            // Frozen snapshot taken at approval time.
            $table->json('snapshot')->nullable();

            // Governance: freeze prevents any later retroactive change.
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'year', 'month']);
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_reports');
    }
};