<?php

use App\Enums\InternalEventTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unifies the two overlapping scheduling tables into a single `personal_events`
 * table — the "Mo Termine / internal admin appointments" layer of the calendar.
 *
 * Before this migration the same concept lived in two places:
 *   - `personal_appointments` — a single user's private appointments
 *   - `internal_events`       — company events with a creator + attendees
 *
 * Both are now rows of `personal_events`, discriminated by the `source`
 * column, so the calendar has exactly one personal layer to query. The legacy
 * tables are intentionally LEFT IN PLACE (frozen, no longer written to) so
 * this migration is fully reversible and no historical data is destroyed.
 */
return new class extends Migration
{
    /** Discriminator values. */
    private const SOURCE_APPOINTMENT = 'personal_appointment';
    private const SOURCE_INTERNAL    = 'internal_event';

    public function up(): void
    {
        Schema::create('personal_events', function (Blueprint $table) {
            $table->id();

            // Owner. For a company event this is the creator.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('title');

            $table->text('description')->nullable();

            $table->enum('event_type', InternalEventTypeEnum::values())
                  ->default(InternalEventTypeEnum::Other->value);

            $table->dateTime('start_at');
            $table->dateTime('end_at');

            $table->string('color_code', 7)->nullable();

            // Private entries are only ever shown to their owner.
            $table->boolean('is_private')->default(false);

            $table->boolean('all_day')->default(false);
            $table->string('location')->nullable();
            $table->json('reminders')->nullable();

            // Which legacy concept this row came from.
            $table->string('source', 32);
            $table->unsignedBigInteger('source_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'start_at']);
            $table->index(['start_at', 'end_at']);
            $table->index(['event_type', 'start_at']);
            $table->index(['source', 'source_id']);
        });

        Schema::create('personal_event_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_event_id')->constrained('personal_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['personal_event_id', 'user_id'], 'uniq_personal_event_attendee');
            $table->index('user_id');
        });

        $this->migrateAppointments();
        $this->migrateInternalEvents();
        $this->migrateAssignees();
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_event_assignees');
        Schema::dropIfExists('personal_events');
    }

    // ── Backfill ───────────────────────────────────────────────────────────

    private function migrateAppointments(): void
    {
        if (! Schema::hasTable('personal_appointments')) {
            return;
        }

        DB::table('personal_appointments')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('personal_events')->insert([
                    'user_id'     => $row->user_id,
                    'title'       => $row->title,
                    'description' => $row->description,
                    // A private appointment has no company-wide event type.
                    'event_type'  => InternalEventTypeEnum::Other->value,
                    'start_at'    => $row->start_at,
                    'end_at'      => $row->end_at,
                    'color_code'  => $row->color,
                    'is_private'  => true,
                    'all_day'     => (int) ($row->all_day ?? 0),
                    'location'    => $row->location,
                    'reminders'   => $row->reminders,
                    'source'      => self::SOURCE_APPOINTMENT,
                    'source_id'   => $row->id,
                    'created_at'  => $row->created_at,
                    'updated_at'  => $row->updated_at,
                ]);
            }
        });
    }

    private function migrateInternalEvents(): void
    {
        if (! Schema::hasTable('internal_events')) {
            return;
        }

        DB::table('internal_events')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('personal_events')->insert([
                    'user_id'     => $row->created_by,
                    'title'       => $row->title,
                    'description' => $row->description,
                    'event_type'  => $row->event_type,
                    'start_at'    => $row->start_at,
                    'end_at'      => $row->end_at,
                    'color_code'  => $row->color,
                    'is_private'  => false,
                    'all_day'     => (int) ($row->all_day ?? 0),
                    'location'    => $row->location,
                    'reminders'   => $row->reminders,
                    'source'      => self::SOURCE_INTERNAL,
                    'source_id'   => $row->id,
                    'created_at'  => $row->created_at,
                    'updated_at'  => $row->updated_at,
                    'deleted_at'  => $row->deleted_at,
                ]);
            }
        });
    }

    /**
     * Carry the attendee list across, remapping internal_event_id to the new
     * personal_events id via the source_id we just recorded.
     */
    private function migrateAssignees(): void
    {
        if (! Schema::hasTable('internal_event_assignees')) {
            return;
        }

        $idMap = DB::table('personal_events')
            ->where('source', self::SOURCE_INTERNAL)
            ->whereNotNull('source_id')
            ->pluck('id', 'source_id');

        if ($idMap->isEmpty()) {
            return;
        }

        $rows = [];

        foreach (DB::table('internal_event_assignees')->orderBy('id')->cursor() as $row) {
            $newId = $idMap[$row->internal_event_id] ?? null;

            if ($newId === null) {
                continue;
            }

            $rows[] = [
                'personal_event_id' => $newId,
                'user_id'           => $row->user_id,
                'created_at'        => $row->created_at,
                'updated_at'        => $row->updated_at,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('personal_event_assignees')->insert($chunk);
        }
    }
};
