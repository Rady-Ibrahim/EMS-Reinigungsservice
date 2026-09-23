<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CompleteExecutionRequest;
use App\Http\Requests\Api\V1\StartExecutionRequest;
use App\Models\FixObject;
use App\Models\FixObjectExecution;
use App\Models\FixObjectSchedule;
use App\Services\FixObjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FixObjectController extends Controller
{
    public function __construct(private readonly FixObjectService $service)
    {
    }

    /**
     * GET /api/v1/fix-objects
     * Returns only fix objects assigned to the authenticated employee.
     * Zero financial data exposed.
     */
    public function index(Request $request): JsonResponse
    {
        $userId     = $request->user()->id;
        $fixObjects = FixObject::active()
            ->forEmployee($userId)
            ->with(['location:id,name,street,house_number,postal_code,city,latitude,longitude', 'customer:id,name'])
            ->get()
            ->map(fn($fo) => $this->formatFixObject($fo));

        return response()->json(['data' => $fixObjects]);
    }

    /**
     * GET /api/v1/fix-objects/{id}
     * Employee can only view fix objects they are assigned to.
     */
    public function show(Request $request, FixObject $fixObject): JsonResponse
    {
        $userId = $request->user()->id;

        $isAssigned = $fixObject->assignments()
            ->where('user_id', $userId)
            ->active()
            ->exists();

        abort_unless($isAssigned, Response::HTTP_NOT_FOUND);

        $fixObject->load(['location', 'customer:id,name']);

        return response()->json(['data' => $this->formatFixObject($fixObject)]);
    }

    /**
     * GET /api/v1/fix-objects/{id}/schedules
     * Employee sees their own schedules only — not other employees' executions.
     */
    public function schedules(Request $request, FixObject $fixObject): JsonResponse
    {
        $userId = $request->user()->id;

        // Verify assignment
        abort_unless(
            $fixObject->assignments()->where('user_id', $userId)->active()->exists(),
            Response::HTTP_NOT_FOUND
        );

        $from = $request->input('from', now()->toDateString());
        $to   = $request->input('to', now()->addDays(30)->toDateString());

        $schedules = $fixObject->schedules()
            ->forDateRange($from, $to)
            ->with(['executions' => fn($q) => $q->where('user_id', $userId)])
            ->orderBy('scheduled_date')
            ->get()
            ->map(fn($s) => $this->formatSchedule($s, $userId));

        return response()->json(['data' => $schedules]);
    }

    /**
     * POST /api/v1/schedules/{schedule}/start
     * Employee starts a job — creates execution, freezes contract_hours.
     */
    public function startExecution(StartExecutionRequest $request, FixObjectSchedule $schedule): JsonResponse
    {
        $execution = $this->service->startExecution(
            $schedule,
            $request->user()->id,
            $request->validated()
        );

        return response()->json([
            'message' => 'Auftrag gestartet.',
            'data'    => $this->formatExecution($execution),
        ], Response::HTTP_CREATED);
    }

    /**
     * PATCH /api/v1/executions/{execution}/status
     * Advance the workflow status (photos_before → cleaning → photos_after).
     */
    public function updateStatus(Request $request, FixObjectExecution $execution): JsonResponse
    {
        // Employee can only update their own execution
        abort_unless($execution->user_id === $request->user()->id, Response::HTTP_FORBIDDEN);

        $request->validate([
            'status'         => ['required', 'string'],
            'before_photos'  => ['nullable', 'array'],
            'after_photos'   => ['nullable', 'array'],
        ]);

        $nextStatus = \App\Enums\ExecutionStatusEnum::from($request->input('status'));

        $execution = $this->service->advanceStatus(
            $execution,
            $nextStatus,
            $request->only(['before_photos', 'after_photos'])
        );

        return response()->json([
            'message' => 'Status aktualisiert.',
            'data'    => $this->formatExecution($execution),
        ]);
    }

    /**
     * POST /api/v1/executions/{execution}/complete
     * Employee completes the job — records end GPS and finalizes execution.
     */
    public function completeExecution(CompleteExecutionRequest $request, FixObjectExecution $execution): JsonResponse
    {
        abort_unless($execution->user_id === $request->user()->id, Response::HTTP_FORBIDDEN);

        $execution = $this->service->completeExecution($execution, $request->validated());

        return response()->json([
            'message' => 'Auftrag abgeschlossen.',
            'data'    => $this->formatExecution($execution),
        ]);
    }

    // ── Private formatters ─────────────────────────────────────────────────

    private function formatFixObject(FixObject $fo): array
    {
        return [
            'id'             => $fo->id,
            'title'          => $fo->title,
            'frequency'      => $fo->frequency->value,
            'frequency_label'=> $fo->frequency->label(),
            'frequency_days' => $fo->frequency_days,
            'contract_hours' => $fo->contract_hours,
            'time_start'     => $fo->time_start,
            'time_end'       => $fo->time_end,
            'customer'       => ['id' => $fo->customer->id, 'name' => $fo->customer->name],
            'location'       => $fo->location ? [
                'id'        => $fo->location->id,
                'name'      => $fo->location->name,
                'address'   => $fo->location->fullAddress(),
                'latitude'  => $fo->location->latitude,
                'longitude' => $fo->location->longitude,
            ] : null,
            // Financial fields intentionally omitted
        ];
    }

    private function formatSchedule(FixObjectSchedule $schedule, int $userId): array
    {
        $execution = $schedule->executions->first(); // filtered to userId

        return [
            'id'              => $schedule->id,
            'scheduled_date'  => $schedule->scheduled_date->toDateString(),
            'scheduled_start' => $schedule->scheduled_start,
            'scheduled_end'   => $schedule->scheduled_end,
            'status'          => $schedule->status->value,
            'execution'       => $execution ? $this->formatExecution($execution) : null,
        ];
    }

    private function formatExecution(FixObjectExecution $execution): array
    {
        return [
            'id'                      => $execution->id,
            'status'                  => $execution->status->value,
            'status_label'            => $execution->status->label(),
            'actual_start'            => $execution->actual_start?->toIso8601String(),
            'actual_end'              => $execution->actual_end?->toIso8601String(),
            'contract_hours_applied'  => $execution->contract_hours_applied,
            'before_photos'           => $execution->before_photos ?? [],
            'after_photos'            => $execution->after_photos ?? [],
            'allowed_next_statuses'   => array_map(
                fn($s) => $s->value,
                $execution->status->allowedNextStatuses()
            ),
        ];
    }
}
