<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExtraAuftragAssignee;
use App\Models\FixObjectSchedule;
use App\Services\ReassignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ReassignmentController extends Controller
{
    public function __construct(private readonly ReassignmentService $service)
    {
    }

    /**
     * POST /api/v1/schedules/{schedule}/reassign
     * Vorarbeiter only — override a single day with another employee.
     */
    public function reassignSchedule(Request $request, FixObjectSchedule $schedule): JsonResponse
    {
        abort_unless($request->user()->tokenCan('reassign:manage'), Response::HTTP_FORBIDDEN);

        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'reason'  => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $assignment = $this->service->reassignSchedule(
                $schedule,
                $request->integer('user_id'),
                $request->input('reason'),
                false,
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Neuer Mitarbeiter hat einen Terminkonflikt an diesem Tag.', 'errors' => $e->errors()], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'message' => 'Termin umgewiesen.',
            'data'    => [
                'assignee'   => $assignment->user->only(['id', 'name']),
                'schedule_id'=> $schedule->id,
                'date'       => $schedule->scheduled_date->toDateString(),
            ],
        ]);
    }

    /**
     * POST /api/v1/extra-assignees/{assignee}/reassign
     * Vorarbeiter only — swap one team member on an Extra-Auftrag.
     */
    public function reassignExtraAssignee(Request $request, ExtraAuftragAssignee $assignee): JsonResponse
    {
        abort_unless($request->user()->tokenCan('reassign:manage'), Response::HTTP_FORBIDDEN);

        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        try {
            $updated = $this->service->reassignExtraAssignee($assignee, $request->integer('user_id'), false);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Neuer Mitarbeiter hat einen Terminkonflikt.', 'errors' => $e->errors()], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'message' => 'Mitarbeiter getauscht.',
            'data'    => [
                'assignee_id' => $updated->id,
                'user'        => $updated->user->only(['id', 'name']),
                'order_id'    => $updated->extra_auftrag_id,
            ],
        ]);
    }
}