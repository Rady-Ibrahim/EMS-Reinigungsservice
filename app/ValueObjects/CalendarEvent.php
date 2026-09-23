<?php

namespace App\ValueObjects;

use App\Enums\CalendarEventTypeEnum;
use Carbon\CarbonInterface;

/**
 * Normalized, immutable calendar event — the Unified Event Schema every
 * source model is flattened into (Fix, Extra, Shift, Personal, Internal,
 * Teamup-external). Produced by CalendarEngineService; never persisted.
 */
final readonly class CalendarEvent
{
    /**
     * @param  array<int>  $employeeIds   Employees whose time this event books
     * @param  array<string, mixed>  $metadata  customer/location/detail_url/…
     * @param  array<int, array<string, mixed>>  $conflicts  filled by ConflictCheckerService
     */
    public function __construct(
        public string $id,                      // "fix_schedule:123"
        public CalendarEventTypeEnum $type,
        public string $layer,                   // jobs|shifts|personal|internal|teamup
        public string $title,
        public CarbonInterface $startAt,
        public CarbonInterface $endAt,
        public bool $allDay,
        public string $color,
        public array $employeeIds = [],
        public ?int $ownerId = null,
        public ?string $status = null,
        public ?string $statusLabel = null,
        public ?string $location = null,
        public ?int $sourceId = null,
        public array $metadata = [],
        public array $conflicts = [],
    ) {
    }

    public function withConflicts(array $conflicts): static
    {
        return new static(
            id: $this->id,
            type: $this->type,
            layer: $this->layer,
            title: $this->title,
            startAt: $this->startAt,
            endAt: $this->endAt,
            allDay: $this->allDay,
            color: $this->color,
            employeeIds: $this->employeeIds,
            ownerId: $this->ownerId,
            status: $this->status,
            statusLabel: $this->statusLabel,
            location: $this->location,
            sourceId: $this->sourceId,
            metadata: $this->metadata,
            conflicts: $conflicts,
        );
    }

    public function hasConflicts(): bool
    {
        return $this->conflicts !== [];
    }

    public function overlaps(self $other): bool
    {
        return $this->startAt->lt($other->endAt) && $other->startAt->lt($this->endAt);
    }

    /** Shares at least one booked employee with the other event. */
    public function sharesEmployeeWith(self $other): bool
    {
        return array_intersect($this->employeeIds, $other->employeeIds) !== [];
    }

    public function toArray(): array
    {
        // FullCalendar expects date-only strings (end exclusive) for all-day events
        $start = $this->allDay
            ? $this->startAt->toDateString()
            : $this->startAt->toIso8601String();

        $end = $this->allDay
            ? $this->endAt->copy()->addDay()->toDateString()
            : $this->endAt->toIso8601String();

        return [
            'id'             => $this->id,
            'type'           => $this->type->value,
            'type_label'     => $this->type->label(),
            'layer'          => $this->layer,
            'title'          => $this->title,
            'start'          => $start,
            'end'            => $end,
            'all_day'        => $this->allDay,
            'color'          => $this->color,
            'employee_ids'   => $this->employeeIds,
            'owner_id'       => $this->ownerId,
            'status'         => $this->status,
            'status_label'   => $this->statusLabel,
            'location'       => $this->location,
            'source_id'      => $this->sourceId,
            'detail_url'     => $this->metadata['detail_url'] ?? null,
            'metadata'       => collect($this->metadata)->except(['detail_url'])->all(),
            'has_conflict'   => $this->hasConflicts(),
            'conflicts'      => $this->conflicts,
        ];
    }
}