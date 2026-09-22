<?php

use App\Enums\CustomerStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('contact_person')->nullable();
            $table->enum('status', CustomerStatusEnum::values())->default(CustomerStatusEnum::Active->value);

            // Internal admin notes — not exposed to API
            $table->text('notes')->nullable();

            // Future customer portal support
            $table->boolean('portal_access')->default(false);
            $table->string('portal_email')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
