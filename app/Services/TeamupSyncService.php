<?php

namespace App\Services;

use App\Enums\TeamupSyncStatusEnum;
use App\Models\EmployeeShift;
use App\Models\ExtraAuftrag;
use App\Models\FixObjectSchedule;
use App\Models\InternalEvent;
use App\Models\PersonalAppointment;
use App\Models\TeamupSetting;
use App\Models\TeamupSyncState;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Teamup Integration Engine — keeps a remote Teamup calendar in sync with the
 * unified EMS calendar.
 *
 * Architecture:
 *   - Model observers call markPending() on every mutation (cheap DB write).
 *   - ems:teamup-sync (or the admin "Sync now" button) flushes pending pushes
 *     and pulls changes made on the Teamup side.
 *   - Push: fix schedules, extra orders, shifts, personal + internal events.
 *   - Pull: adopts remote title/time/location edits for owned types
 *     (personal, internal, shift); fix/extra remain EMS-authoritative.
 *   - Teamup-only events (no sync state) surface read-only in the calendar.
 */
class TeamupSyncService
{
    /** Types whose sanitized content is imported FROM Teamup on pull. */
    private const EDITABLE_TYPES = [
        'personal_appointment',
        'internal_event',
        'employee_shift',
    ];

    public function __construct(private readonly TeamupClient $client)
    {
    }

    // ── Ingress: called from model observers ───────────────────────────────

    public function markPending(Model $entity): void
    {
        $type = $this->entityTypeFor($entity);

        if (! $type) {
            return;
        }
        if (! TeamupSetting::current()->isConfigured()) {
            return;
        }

        TeamupSyncState::updateOrCreate(
            ['entity_type' => $type, 'entity_id' => $entity->getKey()],
            [
                'status'         => TeamupSyncStatusEnum::Pending,
                'error'          => null,
                'last_pushed_at' => null,
            ]
        );
    }

    /**
     * Seed pending states for existing events (used when Teamup is first enabled).
     */
    public function prime(CarbonInterface $from, CarbonInterface $to): int
    {
        $engine = app(CalendarEngineService::class);
        $events = $engine->forRange($from, $to, ['fix', 'extra', 'shifts', 'personal', 'internal']);

        $created = 0;
        foreach ($events as $event) {
            if ($event->sourceId === null) {
                continue;
            }

            $created += (int) TeamupSyncState::query()
                ->firstOrCreate(
                    ['entity_type' => $event->type->value, 'entity_id' => $event->sourceId]
                )->wasRecentlyCreated;
        }

        return $created;
    }

    // ── Full sync (command + admin button) ─────────────────────────────────

    /**
     * @return array<string, mixed> summary of pushed/pulled/failed etc.
     */
    public function syncAll(int $limit = 200): array
    {
        $settings = TeamupSetting::current();

        if (! $settings->isConfigured()) {
            return ['enabled' => false];
        }

        $summary = [
            'enabled' => true,
            'pushed'  => 0,
            'skipped' => 0,
            'failed'  => 0,
            'deleted' => 0,
            'pulled'  => 0,
            'errors'  => [],
        ];

        $states = TeamupSyncState::pending()->orderBy('id')->limit($limit)->get();

        foreach ($states as $state) {
            try {
                $outcome = $this->pushState($state);
                $summary[$outcome]++;
            } catch (\Throwable $e) {
                $state->update([
                    'status' => TeamupSyncStatusEnum::Failed,
                    'error'  => $e->getMessage(),
                ]);
                $summary['failed']++;
                $summary['errors'][] = sprintf('%s#%s — %s', $state->entity_type, $state->entity_id, $e->getMessage());
            }
        }

        try {
            $pullSummary = $this->pull($settings);
            foreach ($pullSummary as $key => $value) {
                $summary[$key] += $value;
            }
        } catch (\Throwable $e) {
            $summary['errors'][] = 'Pull fehlgeschlagen: '.$e->getMessage();
        }

        $settings->update(['last_synced_at' => now()]);

        return $summary;
    }

    // ── Outbound (push) ────────────────────────────────────────────────────

    /**
     * Push one sync state; returns 'pushed' | 'skipped' | 'deleted'.
     */
    public function pushState(TeamupSyncState $state): string
    {
        $entity = $this->resolveEntity($state->entity_type, $state->entity_id);

        if (! $entity || $this->isTrashed($entity)) {
            $this->forgetRemote($state);

            return 'deleted';
        }

        $payload = $this->payloadFor($entity);

        if ($payload === []) {
            throw new \RuntimeException('Kein Subkalender für Typ konfiguriert.');
        }

        $hash = md5(json_encode($payload));

        // No-op: remote already matches our last push
        if ($state->teamup_event_id && $state->remote_hash === $hash) {
            $state->update(['status' => TeamupSyncStatusEnum::Synced]);

            return 'skipped';
        }

        if ($state->teamup_event_id) {
            $remote = $this->client->updateEvent($state->teamup_event_id, $payload);
        } else {
            $remote = $this->client->createEvent($payload);
        }

        $remoteId = (string) ($remote['id'] ?? '');

        if ($remoteId === '') {
            throw new \RuntimeException('Leere Antwort von Teamup.');
        }

        $state->update([
            'status'         => TeamupSyncStatusEnum::Synced,
            'teamup_event_id'=> $remoteId,
            'remote_hash'    => $hash,
            'error'          => null,
            'last_pushed_at' => now(),
        ]);

        return 'pushed';
    }

    // ── Inbound (pull) ─────────────────────────────────────────────────────

    /**
     * Import modifications made on the Teamup side since the last sync.
     *
     * @return array{pushed: int, skipped: int, failed: int, deleted: int, pulled: int}
     */
    public function pull(TeamupSetting $settings): array
    {
        $result = ['pushed' => 0, 'skipped' => 0, 'failed' => 0, 'deleted' => 0, 'pulled' => 0];

        // Overlap of 5 minutes protects against events in flight during the last sync
        $cursor = $settings->last_synced_at
            ? $settings->last_synced_at->copy()->subMinutes(5)->timestamp
            : now()->subDay()->timestamp;

        $remoteEvents = $this->client->fetchChanged($cursor);

        foreach ($remoteEvents as $remote) {
            $remoteId = (string) ($remote['id'] ?? '');

            if ($remoteId === '') {
                continue;
            }

            $state = TeamupSyncState::where('teamup_event_id', $remoteId)->first();

            if (! $state) {
                continue; // Teamup-only event → external read-only layer
            }

            $isDeleted = ! empty($remote['delete_dt']);

            if ($isDeleted) {
                if (in_array($state->entity_type, self::EDITABLE_TYPES, true)) {
                    DB::transaction(fn() => $this->deleteLocalEntity($state->entity_type, $state->entity_id));
                }
                $state->update([
                    'status'         => TeamupSyncStatusEnum::Deleted,
                    'last_pulled_at' => now(),
                ]);
                $result['deleted']++;
                continue;
            }

            if (in_array($state->entity_type, self::EDITABLE_TYPES, true)) {
                $entity = $this->resolveEntity($state->entity_type, $state->entity_id);

                if ($entity && ! $this->isTrashed($entity)) {
                    $changed = Model::withoutEvents(fn() => $this->applyRemoteChanges($entity, $remote));

                    if ($changed) {
                        $result['pulled']++;
                    }

                    // Adopt the incoming state so we never instantly re-push it back
                    $fresh = $this->resolveEntity($state->entity_type, $state->entity_id);
                    if ($fresh) {
                        $state->update([
                            'status'         => TeamupSyncStatusEnum::Synced,
                            'remote_hash'    => md5(json_encode($this->payloadFor($fresh))),
                            'last_pulled_at' => now(),
                        ]);
                    }
                }
            } else {
                // fix/extra: EMS-authoritative — ignore remote content edits
                $state->update(['last_pulled_at' => now()]);
            }
        }

        return $result;
    }

    // ── Payload building ───────────────────────────────────────────────────

    /**
     * Build the Teamup event payload for a local entity. Empty array when the
     * event type has no subcalendar configured.
     *
     * @return array<string, mixed>
     */
    public function payloadFor(Model $entity): array
    {
        $settings = TeamupSetting::current();
        $type     = $this->entityTypeFor($entity);

        if (! $type) {
            return [];
        }

        $subcalendarId = $settings->subcalendarFor($type);

        if (! $subcalendarId) {
            return [];
        }

        $timezone = $settings->timezone ?: 'Europe/Berlin';
        $fields = $this->entityFields($entity);

        $localize = fn(?CarbonInterface $dt) => $dt?->copy()->setTimezone($timezone)->format('Y-m-d\TH:i:s');

        $payload = [
            'subcalendar_id' => $subcalendarId,
            'title'          => $fields['title'],
            'start_dt'       => $fields['all_day'] ? $fields['start']->toDateString() : $localize($fields['start']),
            'end_dt'         => $fields['all_day'] ? $fields['end']->copy()->addDay()->toDateString() : $localize($fields['end']),
            'all_day'        => (bool) $fields['all_day'],
        ];

        if (! empty($fields['location'])) {
            $payload['location'] = $fields['location'];
        }
        if (! empty($fields['notes'])) {
            $payload['notes'] = $fields['notes'];
        }

        return $payload;
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function entityFields(Model $entity): array
    {
        $fields = ['title' => null, 'start' => null, 'end' => null, 'all_day' => false, 'location' => null, 'notes' => null];

        if ($entity instanceof PersonalAppointment || $entity instanceof InternalEvent) {
            $fields['title']     = $entity->title;
            $fields['start']     = $entity->start_at;
            $fields['end']       = $entity->end_at;
            $fields['all_day']   = $entity->all_day;
            $fields['location']  = $entity->location;
            $fields['notes']     = $entity->description;

            return $fields;
        }

        if ($entity instanceof EmployeeShift) {
            $fields['title']    = $entity->title;
            $fields['start']    = $entity->start_at;
            $fields['end']      = $entity->end_at;
            $fields['all_day']  = $entity->all_day;
            $fields['notes']    = $entity->notes;

            return $fields;
        }

        if ($entity instanceof FixObjectSchedule) {
            $fixObject = $entity->fixObject;

            if (! $fixObject) {
                return $fields;
            }

            $date = Carbon::parse($entity->scheduled_date)->toDateString();

            if ($entity->scheduled_start && $entity->scheduled_end) {
                $fields['start']   = Carbon::parse($date.' '.$entity->scheduled_start);
                $fields['end']     = Carbon::parse($date.' '.$entity->scheduled_end);
                $fields['all_day'] = false;
            } else {
                $fields['start']   = Carbon::parse($date)->startOfDay();
                $fields['end']     = Carbon::parse($date)->endOfDay();
                $fields['all_day'] = true;
            }
            $fields['title'] = $fixObject->title;
            $fields['notes'] = sprintf('Fixobjekt-Kontraktstunden: %s', $fixObject->contract_hours);

            return $fields;
        }

        if ($entity instanceof ExtraAuftrag) {
            $date = $entity->scheduled_date;
            $fields['title'] = $entity->title;

            if ($entity->scheduled_time_start) {
                $start = Carbon::parse($date->toDateString().' '.$entity->scheduled_time_start);
                $hours = (float) ($entity->estimated_hours ?: 1);
                $fields['start']   = $start;
                $fields['end']     = $start->copy()->addHours(max($hours, 0.25));
                $fields['all_day'] = false;
            } else {
                $fields['start']   = $date->copy()->startOfDay();
                $fields['end']     = $date->copy()->endOfDay();
                $fields['all_day'] = true;
            }

            return $fields;
        }

        return $fields;
    }

    private function forgetRemote(TeamupSyncState $state): void
    {
        if ($state->teamup_event_id) {
            Model::withoutEvents(fn() => $this->client->deleteEvent($state->teamup_event_id));
        }

        $state->update([
            'status'          => TeamupSyncStatusEnum::Deleted,
            'teamup_event_id' => null,
            'remote_hash'     => null,
        ]);
    }

    private function deleteLocalEntity(string $type, int $id): void
    {
        switch ($type) {
            case 'personal_appointment':
                PersonalAppointment::where('id', $id)->forceDelete();
                break;
            case 'employee_shift':
                EmployeeShift::where('id', $id)->forceDelete();
                break;
            case 'internal_event':
                InternalEvent::where('id', $id)->withTrashed()->delete();
                break;
        }
    }

    /**
     * Compare remote fields against the local entity and apply changes.
     */
    private function applyRemoteChanges(Model $entity, array $remote): bool
    {
        $allDay = (bool) ($remote['all_day'] ?? false);
        $start  = $this->parseRemoteDateTime($remote['start_dt'] ?? null, $allDay, true);
        $end    = $this->parseRemoteDateTime($remote['end_dt'] ?? null, $allDay, false);

        if (! $start || ! $end || $start->gte($end)) {
            return false;
        }

        $notes = strip_tags((string) ($remote['notes'] ?? ''));

        $dirty  = [];
        $dirty['title']   = (string) ($remote['title'] ?? $entity->title);
        $dirty['start_at'] = $start;
        $dirty['end_at']   = $end;
        $dirty['all_day']  = $allDay;

        if ($entity instanceof PersonalAppointment || $entity instanceof InternalEvent) {
            $dirty['location']    = ($remote['location'] ?? '') !== '' ? $remote['location'] : null;
            $dirty['description'] = $notes !== '' ? $notes : null;
        } elseif ($entity instanceof EmployeeShift) {
            $dirty['notes'] = $notes !== '' ? $notes : null;
        }

        $changed = false;
        foreach ($dirty as $field => $value) {
            if ((string) $entity->getAttribute($field) !== (string) $value) {
                $changed = true;
                break;
            }
        }

        if ($changed) {
            $entity->update($dirty);
        }

        return $changed;
    }

    /**
     * Parse a Teamup datetime. All-day events use date-only values with an
     * EXCLUSIVE end date.
     */
    private function parseRemoteDateTime(?string $value, bool $allDay, bool $isStart): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($allDay && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $day = Carbon::parse($value);

            return $isStart ? $day->copy()->startOfDay() : $day->copy()->subDay()->endOfDay();
        }

        return Carbon::parse($value);
    }

    private function entityTypeFor(Model $entity): ?string
    {
        return match (get_class($entity)) {
            PersonalAppointment::class => 'personal_appointment',
            InternalEvent::class       => 'internal_event',
            EmployeeShift::class       => 'employee_shift',
            FixObjectSchedule::class   => 'fix_schedule',
            ExtraAuftrag::class        => 'extra_auftrag',
            default                    => null,
        };
    }

    private function resolveEntity(string $type, int $id): ?Model
    {
        return match ($type) {
            'personal_appointment' => PersonalAppointment::find($id),
            'internal_event'       => InternalEvent::withTrashed()->find($id),
            'employee_shift'       => EmployeeShift::find($id),
            'fix_schedule'         => FixObjectSchedule::find($id),
            'extra_auftrag'        => ExtraAuftrag::withTrashed()->find($id),
            default                => null,
        };
    }

    private function isTrashed(Model $entity): bool
    {
        return method_exists($entity, 'trashed') && $entity->trashed();
    }
}