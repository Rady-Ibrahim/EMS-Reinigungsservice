<?php

namespace App\Services;

use App\Models\InternalEvent;
use App\Models\InternalEventAssignee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Internal company events (Begehung, Besprechung, Anruf, Erinnerung).
 * Not work orders — never paid hours.
 */
class InternalEventService
{
    public function __construct(
        private readonly ConflictCheckerService $conflicts,
        private readonly TeamupSyncService $teamup
    ) {
    }

    /**
     * @param  array<int>  $attendeeIds
     */
    public function create(array $data, array $attendeeIds = [], bool $force = false): InternalEvent
    {
        $start    = Carbon::parse($data['start_at']);
        $end      = Carbon::parse($data['end_at']);
        $occupies = $this->occupiedUserIds($data['created_by'], $attendeeIds);

        $this->conflicts->assertClean(
            $this->conflicts->findWindowConflicts(
                $occupies,
                $start,
                $end,
                (bool) ($data['all_day'] ?? false)
            ),
            $force
        );

        return DB::transaction(function () use ($data, $attendeeIds) {
            $event = InternalEvent::create($data);
            $this->syncAttendees($event, array_unique($attendeeIds));

            return $event->fresh(['creator', 'assignees.user']);
        });
    }

    /**
     * @param  array<int>|null  $attendeeIds  null = keep existing
     */
    public function update(InternalEvent $event, array $data, ?array $attendeeIds = null, bool $force = false): InternalEvent
    {
        $start    = Carbon::parse($data['start_at'] ?? $event->start_at);
        $end      = Carbon::parse($data['end_at'] ?? $event->end_at);
        $occupies = $this->occupiedUserIds(
            (int) ($data['created_by'] ?? $event->created_by),
            $attendeeIds ?? $event->assignees->pluck('user_id')->all()
        );

        $this->conflicts->assertClean(
            $this->conflicts->findWindowConflicts(
                $occupies,
                $start,
                $end,
                (bool) ($data['all_day'] ?? $event->all_day),
                ['internal_event:'.$event->id]
            ),
            $force
        );

        return DB::transaction(function () use ($event, $data, $attendeeIds) {
            $event->update($data);

            if ($attendeeIds !== null) {
                $this->syncAttendees($event, array_unique($attendeeIds));
            }

            return $event->fresh(['creator', 'assignees.user']);
        });
    }

    public function delete(InternalEvent $event): void
    {
        $event->delete();
    }

    /**
     * Replace the attendee list (diff-based to preserve existing attendance rows).
     *
     * @param  array<int>  $attendeeIds
     */
    public function syncAttendees(InternalEvent $event, array $attendeeIds): InternalEvent
    {
        $current = $event->assignees()->pluck('user_id')->all();

        $toAdd = array_diff($attendeeIds, $current);
        $toRemove = array_diff($current, $attendeeIds);

        if ($toRemove !== []) {
            $event->assignees()
                ->whereIntegerInRaw('user_id', array_values($toRemove))
                ->delete();
        }

        foreach ($toAdd as $userId) {
            InternalEventAssignee::create([
                'internal_event_id' => $event->id,
                'user_id'           => (int) $userId,
            ]);
        }

        // Attendee changes affect "who is booked" → queue Teamup re-push
        $this->teamup->markPending($event);

        return $event->fresh(['creator', 'assignees.user']);
    }

    private function occupiedUserIds(int $creatorId, array $attendeeIds): array
    {
        return array_values(array_unique(array_merge([$creatorId], $attendeeIds)));
    }
}