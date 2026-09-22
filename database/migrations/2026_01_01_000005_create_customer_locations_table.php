<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Location identity
            $table->string('name');          // e.g. "Büro München Nord"
            $table->string('street');
            $table->string('house_number', 20);
            $table->string('postal_code', 10);
            $table->string('city');
            $table->string('country', 5)->default('DE');

            // GPS coordinates
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Contact at this location
            $table->string('contact_person')->nullable();
            $table->string('contact_phone', 30)->nullable();

            // Sensitive operational data — access restricted in API layer
            $table->text('access_instructions')->nullable();  // Vorarbeiter + Admin
            $table->text('security_code')->nullable();        // encrypted, Admin only

            // Service configuration
            $table->json('service_checklist')->nullable();    // default checklist items
            $table->json('working_days')->nullable();         // ["Mon","Tue","Wed",...]

            $table->time('working_hours_start')->nullable();
            $table->time('working_hours_end')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_locations');
    }
};
