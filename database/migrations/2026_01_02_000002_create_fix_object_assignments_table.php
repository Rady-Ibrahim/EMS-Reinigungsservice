<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fix_object_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fix_object_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->date('assigned_from');
            $table->date('assigned_until')->nullable(); // null = still active

            $table->text('notes')->nullable();

            $table->timestamps();

            // Prevent duplicate active assignments for the same employee + fix_object
            $table->index(['fix_object_id', 'user_id', 'assigned_until'], 'idx_foa_object_user_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fix_object_assignments');
    }
};
