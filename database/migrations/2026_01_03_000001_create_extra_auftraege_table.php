<?php

use App\Enums\ExtraAuftragStatusEnum;
use App\Enums\ExtraOrderTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_auftraege', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained('customer_locations')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->enum('order_type', ExtraOrderTypeEnum::values())
                  ->default(ExtraOrderTypeEnum::Other->value);

            $table->date('scheduled_date');
            $table->time('scheduled_time_start')->nullable();

            $table->decimal('estimated_hours', 5, 2)->nullable();

            // Admin sets this — determines if travel time counts toward paid hours
            $table->boolean('is_travel_time_paid')->default(false);

            $table->enum('status', ExtraAuftragStatusEnum::values())
                  ->default(ExtraAuftragStatusEnum::Pending->value);

            // Admin-defined checklist template: [{"task": "...", "completed": false}]
            $table->json('checklist_template')->nullable();

            // Financial — encrypted, Admin-only
            $table->text('price')->nullable();          // encrypted
            $table->text('internal_cost')->nullable();  // encrypted

            // Admin internal notes — never exposed to employees
            $table->text('internal_notes')->nullable();

            $table->string('cancelled_reason')->nullable();

            // Offline sync
            $table->uuid('offline_uuid')->nullable()->unique();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'scheduled_date']);
            $table->index(['status', 'scheduled_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_auftraege');
    }
};
