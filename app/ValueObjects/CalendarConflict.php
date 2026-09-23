<?php

namespace App\ValueObjects;

use App\Enums\CalendarEventTypeEnum;
use Carbon\CarbonInterface;

/**
 * One detected double-booking for one employee between two calendar events.
 * Produced by ConflictCheckerService; immutable.
 */
final readonly class CalendarConflict
{
    public function __construct(
        public int $employeeId,
        public string $employeeName,
        public string $eventAId,
        public string $eventATitle,
        public CalendarEventTypeEnum $eventAType,
        public string $eventBId,
        public string $eventBTitle,
        public CalendarEventTypeEnum $eventBType,
        public ?CarbonInterface $overlapStart,
        public ?CarbonInterface $overlapEnd,
        public string $type = 'double_booking',
    ) {
    }

    public function message(): string
    {
        return sprintf(
            '%s: „%s“ überschneidet sich mit „%s“.',
            $this->employeeName,
            $this->eventATitle,
            $this->eventBTitle
        );
    }

    public function involves(string $eventId): bool
    {
        return $this->eventAId === $eventId || $this->eventBId === $eventId;
    }

    public function toArray(): array
    {
        return [
            'type'          => $this->type,
            'employee_id'   => $this->employeeId,
            'employee_name' => $this->employeeName,
            'event_a'       => ['id' => $this->eventAId, 'title' => $this->eventATitle, 'type' => $this->eventAType->value],
            'event_b'       => ['id' => $this->eventBId, 'title' => $this->eventBTitle, 'type' => $this->eventBType->value],
            'overlap_start' => $this->overlapStart?->toIso8601String(),
            'overlap_end'   => $this->overlapEnd?->toIso8601String(),
            'message'       => $this->message(),
        ];
    }
}