<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAppointmentRequest;
use App\Http\Requests\Api\V1\UpdateAppointmentRequest;
use App\Models\PersonalAppointment;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $service)
    {
    }

    /**
     * GET /api/v1/appointments — own personal appointments (Meine Termine).
     */
    public function index(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->addMonths(2)->endOfMonth()->toDateString());

        $appointments = PersonalAppointment::forUser($request->user()->id)
            ->forDateRange($from, $to)
            ->orderBy('start_at')
            ->get()
            ->map(fn($a) => $this->format($a));

        return response()->json(['data' => $appointments]);
    }

    /**
     * POST /api/v1/appointments
     * Personal appointments never require force — a blocked slot stays blocked.
     */
    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        try {
            $appointment = $this->service->create(
                $request->validated() + ['user_id' => $request->user()->id],
                false
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Termin überschneidet sich mit einer bestehenden Buchung.', 'errors' => $e->errors()], Response::HTTP_CONFLICT);
        }

        return response()->json(['data' => $this->format($appointment)], Response::HTTP_CREATED);
    }

    /**
     * PATCH /api/v1/appointments/{appointment} — owner only.
     */
    public function update(UpdateAppointmentRequest $request, PersonalAppointment $appointment): JsonResponse
    {
        abort_unless($appointment->user_id === $request->user()->id, Response::HTTP_FORBIDDEN);
        abort_unless($request->user()->tokenCan('appointments:manage'), Response::HTTP_FORBIDDEN);

        try {
            $appointment = $this->service->update($appointment, $request->validated(), false);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Termin überschneidet sich mit einer bestehenden Buchung.', 'errors' => $e->errors()], Response::HTTP_CONFLICT);
        }

        return response()->json(['data' => $this->format($appointment)]);
    }

    /**
     * DELETE /api/v1/appointments/{appointment} — owner only.
     */
    public function destroy(Request $request, PersonalAppointment $appointment): JsonResponse
    {
        abort_unless($appointment->user_id === $request->user()->id, Response::HTTP_FORBIDDEN);

        $this->service->delete($appointment);

        return response()->json(['message' => 'Termin gelöscht.'], Response::HTTP_OK);
    }

    private function format(PersonalAppointment $appointment): array
    {
        return [
            'id'          => $appointment->id,
            'title'       => $appointment->title,
            'start_at'    => $appointment->start_at->toIso8601String(),
            'end_at'      => $appointment->end_at->toIso8601String(),
            'all_day'     => $appointment->all_day,
            'location'    => $appointment->location,
            'color'       => $appointment->color,
            'description' => $appointment->description,
        ];
    }
}