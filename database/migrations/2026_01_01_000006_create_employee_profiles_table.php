<?php

use App\Enums\ContractTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();

            // 1:1 with users — only Vorarbeiter and Mitarbeiter
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Calendar display color (hex, e.g. "#3b82f6")
            $table->string('calendar_color', 7)->default('#6b7280');

            // Official employee number
            $table->string('employee_number', 20)->nullable()->unique();

            // Contact
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();

            // Financial — stored ENCRYPTED via Laravel's encrypted cast
            // Values are application-encrypted before reaching the DB column
            $table->text('iban')->nullable();             // encrypted
            $table->text('hourly_rate')->nullable();      // encrypted decimal

            $table->enum('contract_type', ContractTypeEnum::values())
                  ->default(ContractTypeEnum::Minijob->value);

            $table->date('joined_at')->nullable();

            // Admin-only notes
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
