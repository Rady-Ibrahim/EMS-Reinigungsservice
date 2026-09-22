<?php

use App\Enums\AuditEventEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Who triggered the event — nullable for system-generated changes
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Polymorphic target (Customer, EmployeeProfile, etc.)
            $table->morphs('auditable'); // adds auditable_type + auditable_id + index

            $table->enum('event', AuditEventEnum::values());

            // Stores only the changed fields, not the full model
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Context for debugging / compliance
            $table->string('url', 2048)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Immutable — no updated_at
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
