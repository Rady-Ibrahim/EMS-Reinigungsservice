<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Admin\Appointment\UpdateAppointmentRequest;
use App\Models\PersonalAppointment;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $service)
    {
    }

    public function index(): View
    {
        $appointments = PersonalAppointment::with(['user:id,name'])
            ->orderByDesc('start_at')
            ->paginate(25);

        return view('admin.calendar.appointments', compact('appointments'));
    }

    public function create(): View
    {
        return view('admin.calendar.appointment_form', [
            'appointment' => new PersonalAppointment(),
            'users'       => $this->users(),
        ]);
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        try {
            $this->service->create($request->validated(), (bool) $request->input('force', false));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.calendar.appointments.index')
                         ->with('success', __('messages.success'));
    }

    public function edit(PersonalAppointment $appointment): View
    {
        return view('admin.calendar.appointment_form', [
            'appointment' => $appointment,
            'users'       => $this->users(),
        ]);
    }

    public function update(UpdateAppointmentRequest $request, PersonalAppointment $appointment): RedirectResponse
    {
        try {
            $this->service->update($appointment, $request->validated(), (bool) $request->input('force', false));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.calendar.appointments.index')
                         ->with('success', __('messages.success'));
    }

    public function destroy(PersonalAppointment $appointment): RedirectResponse
    {
        $this->service->delete($appointment);

        return redirect()->route('admin.calendar.appointments.index')
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