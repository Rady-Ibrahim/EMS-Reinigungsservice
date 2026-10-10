<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teamup_sync_states', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference to the local calendar entity
            // e.g. entity_type = 'personal_appointment', entity_id = 42
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');

            $table->string('teamup_event_id')->nullable();
            $table->string('remote_hash')->nullable();

            // NOTE: values inlined (pending/synced/failed/deleted) — the
            // TeamupSyncStatusEnum was removed with the Teamup integration.
            $table->enum('status', ['pending', 'synced', 'failed', 'deleted'])
                  ->default('pending');

            $table->text('error')->nullable();
            $table->timestamp('last_pushed_at')->nullable();
            $table->timestamp('last_pulled_at')->nullable();

            $table->timestamps();

            // One sync state per local entity
            $table->unique(['entity_type', 'entity_id'], 'uniq_teamup_entity');
            $table->index('teamup_event_id', 'idx_teamup_remote_event');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teamup_sync_states');
    }
};
