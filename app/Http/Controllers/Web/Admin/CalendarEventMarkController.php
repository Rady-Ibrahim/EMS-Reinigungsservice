<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\CalendarEventTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\CalendarEventMark;
use App\Models\EmployeeShift;
use App\Models\ExtraAuftrag;
use App\Models\FixObjectSchedule;
use App\Models\InternalEvent;
use App\Models\PersonalAppointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CalendarEventMarkController extends Controller
{
    public function store(Request $request, string $eventType, int $eventId): RedirectResponse
    {
        abort_unless(in_array($eventType, CalendarEventTypeEnum::values(), true), 404);

        $this->eventQuery($eventType)->findOrFail($eventId);

        CalendarEventMark::updateOrCreate(
            ['event_type' => $eventType, 'event_id' => $eventId],
            ['marked_by' => $request->user()->id]
        );

        return back()->with('success', __('messages.success'));
    }

    public function destroy(string $eventType, int $eventId): RedirectResponse
    {
        abort_unless(in_array($eventType, CalendarEventTypeEnum::values(), true), 404);

        CalendarEventMark::query()
            ->where('event_type', $eventType)
            ->where('event_id', $eventId)
            ->delete();

        return back()->with('success', __('messages.success'));
    }

    private function eventQuery(string $eventType): \Illuminate\Database\Eloquent\Builder
    {
        return match (CalendarEventTypeEnum::from($eventType)) {
            CalendarEventTypeEnum::FixSchedule => FixObjectSchedule::query(),
            CalendarEventTypeEnum::ExtraAuftrag => ExtraAuftrag::query(),
            CalendarEventTypeEnum::EmployeeShift => EmployeeShift::query(),
            CalendarEventTypeEnum::PersonalAppointment => PersonalAppointment::query(),
            CalendarEventTypeEnum::InternalEvent => InternalEvent::query(),
        };
    }
}
