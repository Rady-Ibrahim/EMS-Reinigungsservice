<?php

use App\Enums\ComplaintStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer complaints (Reklamationen).
 *
 * A complaint is raised against a specific location, optionally assigned to the
 * employee who dealt with it, and carries evidence photos. It is the input to
 * the complaint KPIs on the dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('location_id')->constrained('customer_locations')->cascadeOnDelete();

            // Employee responsible for handling the complaint.
            $table->foreignId('assigned_employee_id')->nullable()->constrained('users')->nullOnDelete();

            // Who raised it and when the incident happened.
            $table->string('reported_by', 120)->nullable();
            $table->dateTime('occurred_at')->nullable();

            $table->string('title');
            $table->text('description');

            // Evidence photos (array of stored paths).
            $table->json('photos')->nullable();

            $table->enum('status', ComplaintStatusEnum::values())
                ->default(ComplaintStatusEnum::Pending->value);

            $table->text('resolution')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index('location_id');
            $table->index('assigned_employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
