<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\InternalEventTypeEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\InternalEvent;
use App\Models\User;
use App\Services\InternalEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InternalEventController extends Controller
{
    public function __construct(private readonly InternalEventService $service)
    {
    }

    public function index(): View
    {
        $events = InternalEvent::with(['creator:id,name', 'assignees.user:id,name'])
            ->orderByDesc('start_at')
            ->paginate(25);

        return view('admin.calendar.internal_events', compact('events'));
    }

    public function create(): View
    {
        return view('admin.calendar.internal_event_form', [
            'event'     => new InternalEvent(),
            'users'     => $this->users(),
            'eventTypes'=> InternalEventTypeEnum::values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'event_type'     => ['required', 'in:'.implode(',', InternalEventTypeEnum::values())],
            'start_at'       => ['required', 'date'],
            'end_at'         => ['required', 'date', 'after:start_at'],
            'all_day'        => ['nullable', 'boolean'],
            'location'       => ['nullable', 'string', 'max:255'],
            'attendee_ids'   => ['nullable', 'array'],
            'attendee_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $data = $request->only(['title', 'event_type', 'start_at', 'end_at', 'all_day', 'location', 'description', 'color']);
        $data['created_by'] = auth()->id();

        try {
            $this->service->create(
                $data,
                $request->input('attendee_ids', []),
                (bool) $request->input('force', false)
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.calendar.internal-events.index')
                         ->with('success', __('messages.success'));
    }

    public function edit(InternalEvent $event): View
    {
        $event->load('assignees');

        return view('admin.calendar.internal_event_form', [
            'event'      => $event,
            'users'      => $this->users(),
            'eventTypes' => InternalEventTypeEnum::values(),
        ]);
    }

    public function update(Request $request, InternalEvent $event): RedirectResponse
    {
        $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'event_type'     => ['required', 'in:'.implode(',', InternalEventTypeEnum::values())],
            'start_at'       => ['required', 'date'],
            'end_at'         => ['required', 'date', 'after:start_at'],
            'all_day'        => ['nullable', 'boolean'],
            'location'       => ['nullable', 'string', 'max:255'],
            'attendee_ids'   => ['nullable', 'array'],
            'attendee_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $data = $request->only(['title', 'event_type', 'start_at', 'end_at', 'all_day', 'location', 'description', 'color']);

        try {
            $this->service->update(
                $event,
                $data,
                $request->input('attendee_ids', collect($event->assignees)->pluck('user_id')->map(fn($id) => (int) $id)->all()),
                (bool) $request->input('force', false)
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.calendar.internal-events.index')
                         ->with('success', __('messages.success'));
    }

    public function destroy(InternalEvent $event): RedirectResponse
    {
        $this->service->delete($event);

        return redirect()->route('admin.calendar.internal-events.index')
                         ->with('success', __('messages.success'));
    }

    private function users(): \Illuminate\Support\Collection
    {
        return User::whereIn('role', [
            RoleEnum::Administrator->value,
            RoleEnum::Vorarbeiter->value,
            RoleEnum::Mitarbeiter->value,
        ])->orderBy('name')->get(['id', 'name']);
    }
}