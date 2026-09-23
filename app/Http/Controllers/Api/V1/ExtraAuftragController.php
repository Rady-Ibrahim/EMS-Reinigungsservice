<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssigneeRoleEnum;
use App\Enums\ExtraExecutionStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CompleteExtraOrderRequest;
use App\Http\Requests\Api\V1\RecordArrivalRequest;
use App\Http\Requests\Api\V1\StartTravelRequest;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragExecution;
use App\Services\ExtraAuftragService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExtraAuftragController extends Controller
{
    public function __construct(private readonly ExtraAuftragService $service)
    {
    }

    /**
     * GET /api/v1/extra-orders
     * Returns orders assigned to the authenticated employee.
     * Financial data never exposed.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $orders = ExtraAuftrag::forEmployee($userId)
            ->active()
            ->with(['location:id,name,street,house_number,postal_code,city,latitude,longitude', 'customer:id,name'])
            ->orderBy('scheduled_date')
            ->get()
            ->map(fn($o) => $this->formatOrder($o, $userId));

        return response()->json(['data' => $orders]);
    }

    /**
     * GET /api/v1/extra-orders/{id}
     * Leader sees team list; member sees own info only.
     */
    public function show(Request $request, ExtraAuftrag $extraAuftrag): JsonResponse
    {
        $userId = $request->user()->id;
        abort_unless($extraAuftrag->isAssigned($userId), Response::HTTP_NOT_FOUND);

        $extraAuftrag->load([
            'location', 'customer:id,name',
            'assignees.user:id,name,role',
            'executions' => fn($q) => $q->where('user_id', $userId),
            'travelTracks' => fn($q) => $q->where('user_id', $userId),
        ]);

        $isLeader = $extraAuftrag->isLeader($userId);

        return response()->json([
            'data' => $this->formatOrderDetail($extraAuftrag, $userId, $isLeader),
        ]);
    }

    /**
     * POST /api/v1/extra-orders/{id}/travel/start
     * Any assigned employee can start travel.
     */
    public function startTravel(StartTravelRequest $request, ExtraAuftrag $extraAuftrag): JsonResponse
    {
        $track = $this->service->startTravel(
            $extraAuftrag,
            $request->user()->id,
            $request->validated()
        );

        return response()->json([
            'message' => 'Anfahrt gestartet.',
            'data'    => $this->formatTravelTrack($track),
        ], Response::HTTP_CREATED);
    }

    /**
     * POST /api/v1/extra-orders/{id}/travel/arrive
     * Record arrival — freezes travel_minutes and is_paid.
     */
    public function recordArrival(RecordArrivalRequest $request, ExtraAuftrag $extraAuftrag): JsonResponse
    {
        $track = $this->service->recordArrival(
            $extraAuftrag,
            $request->user()->id,
            $request->validated()
        );

        return response()->json([
            'message' => 'Ankunft registriert.',
            'data'    => $this->formatTravelTrack($track),
        ]);
    }

    /**
     * POST /api/v1/extra-orders/{id}/work/start
     * Any assigned employee starts their work timer.
     */
    public function startWork(Request $request, ExtraAuftrag $extraAuftrag): JsonResponse
    {
        $request->validate([
            'gps_work_start_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_work_start_lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $execution = $this->service->startWork(
            $extraAuftrag,
            $request->user()->id,
            $request->all()
        );

        return response()->json([
            'message' => 'Arbeit gestartet.',
            'data'    => $this->formatExecution($execution),
        ]);
    }

    /**
     * POST /api/v1/executions/{id}/before-photos
     * Leader only — upload before photos.
     */
    public function uploadBeforePhotos(Request $request, ExtraAuftragExecution $execution): JsonResponse
    {
        $this->assertOwnExecution($execution, $request->user()->id);

        $request->validate([
            'before_photos'   => ['required', 'array', 'min:1'],
            'before_photos.*' => ['string'],
        ]);

        $execution = $this->service->uploadBeforePhotos(
            $execution->extraAuftrag,
            $request->user()->id,
            $request->input('before_photos')
        );

        return response()->json([
            'message' => 'Vorher-Fotos gespeichert.',
            'data'    => $this->formatExecution($execution),
        ]);
    }

    /**
     * PATCH /api/v1/executions/{id}/checklist
     * Leader only — update checklist items.
     */
    public function updateChecklist(Request $request, ExtraAuftragExecution $execution): JsonResponse
    {
        $this->assertOwnExecution($execution, $request->user()->id);

        $request->validate([
            'checklist_items'           => ['required', 'array'],
            'checklist_items.*.task'    => ['required', 'string'],
            'checklist_items.*.completed' => ['required', 'boolean'],
        ]);

        $execution = $this->service->updateChecklist(
            $execution->extraAuftrag,
            $request->user()->id,
            $request->input('checklist_items')
        );

        return response()->json([
            'message' => 'Checkliste aktualisiert.',
            'data'    => $this->formatExecution($execution),
        ]);
    }

    /**
     * POST /api/v1/extra-orders/{id}/complete
     * Leader only — close the entire order.
     */
    public function completeOrder(CompleteExtraOrderRequest $request, ExtraAuftrag $extraAuftrag): JsonResponse
    {
        $order = $this->service->completeOrder(
            $extraAuftrag,
            $request->user()->id,
            $request->validated()
        );

        return response()->json([
            'message' => 'Auftrag erfolgreich abgeschlossen.',
            'data'    => ['status' => $order->status->value],
        ]);
    }

    // ── Private formatters ─────────────────────────────────────────────────

    private function formatOrder(ExtraAuftrag $order, int $userId): array
    {
        return [
            'id'                   => $order->id,
            'title'                => $order->title,
            'order_type'           => $order->order_type->value,
            'order_type_label'     => $order->order_type->label(),
            'scheduled_date'       => $order->scheduled_date->toDateString(),
            'scheduled_time_start' => $order->scheduled_time_start,
            'estimated_hours'      => $order->estimated_hours,
            'is_travel_time_paid'  => $order->is_travel_time_paid,
            'status'               => $order->status->value,
            'is_leader'            => $order->isLeader($userId),
            'customer'             => ['id' => $order->customer?->id, 'name' => $order->customer?->name],
            'location'             => $order->location ? [
                'id'        => $order->location->id,
                'name'      => $order->location->name,
                'address'   => $order->location->fullAddress(),
                'latitude'  => $order->location->latitude,
                'longitude' => $order->location->longitude,
            ] : null,
            // Financial never exposed
        ];
    }

    private function formatOrderDetail(ExtraAuftrag $order, int $userId, bool $isLeader): array
    {
        $base = $this->formatOrder($order, $userId);

        // Leader sees team; member sees only themselves
        $base['team'] = $isLeader
            ? $order->assignees->map(fn($a) => [
                'user_id'       => $a->user_id,
                'name'          => $a->user->name,
                'role_in_order' => $a->role_in_order->value,
              ])->values()
            : [];

        $base['checklist_template'] = $order->checklist_template ?? [];
        $base['execution']          = $order->executions->first()
            ? $this->formatExecution($order->executions->first())
            : null;
        $base['travel_track']       = $order->travelTracks->first()
            ? $this->formatTravelTrack($order->travelTracks->first())
            : null;

        return $base;
    }

    private function formatExecution(ExtraAuftragExecution $exec): array
    {
        return [
            'id'              => $exec->id,
            'status'          => $exec->status->value,
            'status_label'    => $exec->status->label(),
            'work_start'      => $exec->work_start?->toIso8601String(),
            'work_end'        => $exec->work_end?->toIso8601String(),
            'work_minutes'    => $exec->work_minutes,
            'paid_minutes'    => $exec->paid_minutes,
            'before_photos'   => $exec->before_photos ?? [],
            'after_photos'    => $exec->after_photos ?? [],
            'checklist_items' => $exec->checklist_items ?? [],
            'allowed_next'    => array_map(fn($s) => $s->value, $exec->status->allowedNextStatuses()),
        ];
    }

    private function formatTravelTrack(\App\Models\TravelTrack $track): array
    {
        return [
            'id'             => $track->id,
            'departure_at'   => $track->departure_at->toIso8601String(),
            'arrival_at'     => $track->arrival_at?->toIso8601String(),
            'travel_minutes' => $track->travel_minutes,
            'is_paid'        => $track->is_paid,
        ];
    }

    private function assertOwnExecution(ExtraAuftragExecution $execution, int $userId): void
    {
        abort_unless($execution->user_id === $userId, Response::HTTP_FORBIDDEN);
    }
}
