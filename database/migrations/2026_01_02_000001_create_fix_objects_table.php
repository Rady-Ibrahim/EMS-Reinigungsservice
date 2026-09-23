<?php

use App\Enums\FixFrequencyEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fix_objects', function (Blueprint $table) {
            $table->id();

            // Ownership — denormalized customer_id for fast queries without join
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained('customer_locations')->restrictOnDelete();

            $table->string('title');

            // Recurrence definition
            $table->enum('frequency', FixFrequencyEnum::values());
            $table->json('frequency_days')->nullable(); // ["Mon","Tue","Wed","Thu","Fri"]

            // Contract hours — the financial/reporting anchor (immutable per contract)
            $table->decimal('contract_hours', 5, 2)->default(1.00);

            // Service window
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();

            // Contract validity
            $table->date('valid_from');
            $table->date('valid_until')->nullable(); // null = open-ended

            $table->boolean('is_active')->default(true);

            // Gold color default for Admin calendar; employees see their own color
            $table->string('calendar_color', 7)->default('#FFD700');

            // ── Financial data (encrypted at rest, Admin-only) ──────────
            $table->text('price_per_month')->nullable();  // encrypted
            $table->text('price_per_hour')->nullable();   // encrypted
            $table->text('internal_cost')->nullable();    // encrypted
            $table->text('profit_margin')->nullable();    // encrypted

            // Admin-only internal notes
            $table->text('internal_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Composite index for calendar queries by customer + active state
            $table->index(['customer_id', 'is_active']);
            $table->index(['location_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fix_objects');
    }
};
