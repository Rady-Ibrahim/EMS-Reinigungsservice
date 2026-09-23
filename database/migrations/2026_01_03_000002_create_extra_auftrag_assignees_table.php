<?php

use App\Enums\AssigneeRoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_auftrag_assignees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('extra_auftrag_id')
                  ->constrained('extra_auftraege')
                  ->cascadeOnDelete();

            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->enum('role_in_order', AssigneeRoleEnum::values())
                  ->default(AssigneeRoleEnum::Member->value);

            $table->timestamps();

            // One assignment per employee per order
            $table->unique(['extra_auftrag_id', 'user_id'], 'uniq_assignee_per_order');

            // Fast lookup for "who is leader of this order"
            $table->index(['extra_auftrag_id', 'role_in_order'], 'idx_order_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_auftrag_assignees');
    }
};
