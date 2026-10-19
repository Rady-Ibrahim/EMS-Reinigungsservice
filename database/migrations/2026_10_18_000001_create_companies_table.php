<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subcontractor companies (شركات).
 *
 * A company groups its own workforce (users.company_id) and can be selected as
 * the responsible party on a Fixobjekt or an Extra-Auftrag. This keeps the
 * simple "one company -> many employees" model requested for the admin panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('contact_person')->nullable();

            // Internal admin notes — never exposed to the mobile API
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
