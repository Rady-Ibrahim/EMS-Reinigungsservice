<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssigneeRoleEnum;
use App\Enums\ExtraExecutionStatusEnum;
use App\Enums\FileVisibilityEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddFieldNoteRequest;
use App\Http\Requests\Api\V1\CompleteExtraOrderRequest;
use App\Http\Requests\Api\V1\EndWorkRequest;
use App\Http\Requests\Api\V1\PauseExecutionRequest;
use App\Http\Requests\Api\V1\RecordArrivalRequest;
use App\Http\Requests\Api\V1\StartTravelRequest;
use App\Models\Document;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragExecution;
use App\Services\ExtraAuftragService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
            'documents',
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
     * POST /api/v1/auftraege/{id}/photos
     * Order-scoped photo upload named in the client specification. Accepts
     * `stage=before|after` and resolves the caller's own leader execution,
     * so the mobile client does not have to know the execution id first.
     */
    public function uploadOrderPhotos(Request $request, ExtraAuftrag $extraAuftrag): JsonResponse
    {
        $request->validate([
            'stage'   => ['nullable', 'in:before,after'],
            'photos'  => ['required', 'array', 'min:1'],
            'photos.*' => ['string', 'max:2048'],
        ]);

        $stage  = $request->input('stage', 'before');
        $photos = $request->input('photos');

        $execution = $stage === 'after'
            ? $this->service->uploadAfterPhotos($extraAuftrag, $request->user()->id, $photos)
            : $this->service->uploadBeforePhotos($extraAuftrag, $request->user()->id, $photos);

        return response()->json([
            'message' => $stage === 'after' ? 'Nachher-Fotos gespeichert.' : 'Vorher-Fotos gespeichert.',
            'data'    => $this->formatExecution($execution),
        ]);
    }

    /**
     * POST /api/v1/extra-orders/{id}/photos/upload
     * Store actual image files privately, then attach their paths to the
     * Vorarbeiter's execution through the existing photo workflow.
     */
    public function uploadOrderPhotoFiles(Request $request, ExtraAuftrag $extraAuftrag): JsonResponse
    {
        abort_unless($extraAuftrag->isLeader($request->user()->id), Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'stage'       => ['required', 'in:before,after'],
            'photos'      => ['required', 'array', 'min:1', 'max:10'],
            'photos.*'    => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ]);

        $execution = $extraAuftrag->executions()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $directory = "extra-auftrag-photos/{$extraAuftrag->id}/{$validated['stage']}";
        $paths = collect($validated['photos'])
            ->map(fn ($photo) => $photo->store($directory, 'local'))
            ->all();

        $execution = $validated['stage'] === 'after'
            ? $this->service->uploadAfterPhotos($extraAuftrag, $request->user()->id, $paths)
            : $this->service->uploadBeforePhotos($extraAuftrag, $request->user()->id, $paths);

        return response()->json([
            'message' => $validated['stage'] === 'after' ? 'Nachher-Fotos gespeichert.' : 'Vorher-Fotos gespeichert.',
            'data'    => $this->formatExecution($execution),
        ], Response::HTTP_CREATED);
    }

    /**
     * Stream a stored field photo only to employees assigned to its order.
     */
    public function downloadOrderPhoto(
        Request $request,
        ExtraAuftrag $extraAuftrag,
        string $stage,
        int $photoIndex
    ): BinaryFileResponse {
        abort_unless($extraAuftrag->isAssigned($request->user()->id), Response::HTTP_NOT_FOUND);
        abort_unless(in_array($stage, ['before', 'after'], true), Response::HTTP_NOT_FOUND);

        $leaderId = $extraAuftrag->leader()->value('user_id');
        abort_if($leaderId === null, Response::HTTP_NOT_FOUND);

        $execution = $extraAuftrag->executions()
            ->where('user_id', $leaderId)
            ->first();
        abort_if($execution === null, Response::HTTP_NOT_FOUND);

        $photos = $execution->getAttribute("{$stage}_photos") ?? [];
        $path = $photos[$photoIndex] ?? null;
        $prefix = "extra-auftrag-photos/{$extraAuftrag->id}/{$stage}/";

        abort_unless(is_string($path) && str_starts_with($path, $prefix), Response::HTTP_NOT_FOUND);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), Response::HTTP_NOT_FOUND);

        $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';
        abort_unless(str_starts_with($mimeType, 'image/'), Response::HTTP_NOT_FOUND);

        return response()->file($disk->path($path), [
            'Content-Type'                => $mimeType,
            'Cache-Control'               => 'private, no-store',
            'X-Content-Type-Options'      => 'nosniff',
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
     * POST /api/v1/extra-orders/{id}/work-end
     * Closing screen: the employee leaves the site and confirms the hand-over.
     *
     * Requires before/after photos, a complete checklist and the security
     * confirmations the site needs; anything missing returns 422 naming the
     * exact field. The order itself is closed separately by the leader.
     */
    public function endWork(EndWorkRequest $request, ExtraAuftrag $extraAuftrag): JsonResponse
    {
        $execution = $this->service->endWork(
            $extraAuftrag,
            $request->user()->id,
            $request->validated()
        );

        return response()->json([
            'message' => 'Auftrag abgeschlossen und Übergabe dokumentiert.',
            'data'    => $this->formatExecution($execution),
        ]);
    }

    /**
     * POST /api/v1/extra-orders/{id}/pause
     * Start or stop the unpaid break. Break minutes are deducted from the
     * paid working time when the execution is closed.
     */
    public function togglePause(PauseExecutionRequest $request, ExtraAuftrag $extraAuftrag): JsonResponse
    {
        $execution = $this->service->togglePause(
            $extraAuftrag,
            $request->user()->id,
            [
                'action'    => $request->input('action'),
                'paused_at' => $request->date('paused_at'),
            ]
        );

        return response()->json([
            'message' => $execution->isPaused() ? 'Pause gestartet.' : 'Pause beendet.',
            'data'    => $this->formatExecution($execution),
        ]);
    }

    /**
     * POST /api/v1/extra-orders/{id}/notes
     * Append a quick field note from the assigned employee.
     */
    public function addNote(AddFieldNoteRequest $request, ExtraAuftrag $extraAuftrag): JsonResponse
    {
        $execution = $this->service->addNote(
            $extraAuftrag,
            $request->user()->id,
            (string) $request->validated('note')
        );

        return response()->json([
            'message' => 'Notiz gespeichert.',
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
            'vehicle_info'         => [
                'label' => $order->vehicle_label,
            ],
            'equipment_info'       => $order->equipment_info ?? [],
            'security'             => [
                'has_alarm_system' => (bool) $order->has_alarm_system,
                'has_key_safe'     => (bool) $order->has_key_safe,
            ],
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

        $base['documents'] = $this->formatDocuments($order);

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

    /**
     * Externally visible documents only — intern documents are admin-only.
     */
    private function formatDocuments(ExtraAuftrag $order): array
    {
        return $order->documents
            ->where('visibility', FileVisibilityEnum::Extern)
            ->map(fn(Document $d) => [
                'id'             => $d->id,
                'title'          => $d->displayTitle(),
                'notes'          => $d->notes,
                'category'       => $d->category->value,
                'category_label' => $d->category->label(),
                'mime_type'      => $d->mime_type,
                'size_bytes'     => (int) $d->size_bytes,
                'download_url'   => route('api.v1.extra-orders.documents.download', [$order->id, $d->id]),
                'uploaded_at'    => $d->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
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
            'before_photos'   => $this->formatPhotoReferences($exec, 'before'),
            'after_photos'    => $this->formatPhotoReferences($exec, 'after'),
            'checklist_items' => $exec->checklist_items ?? [],
            'allowed_next'    => array_map(fn($s) => $s->value, $exec->status->allowedNextStatuses()),

            // Closing screen (Auftrag beenden & Übergabe)
            'key_returned'     => (bool) $exec->key_returned,
            'windows_closed'   => (bool) $exec->windows_closed,
            'alarm_activated'  => (bool) $exec->alarm_activated,
            'shift_note'       => $exec->shift_note,
            'is_paused'        => $exec->isPaused(),
            'pause_started_at' => $exec->pause_started_at?->toIso8601String(),
            'pause_minutes'    => $exec->pause_minutes,
        ];
    }

    private function formatPhotoReferences(ExtraAuftragExecution $execution, string $stage): array
    {
        $photos = array_values($execution->getAttribute("{$stage}_photos") ?? []);
        $prefix = "extra-auftrag-photos/{$execution->extra_auftrag_id}/{$stage}/";

        foreach ($photos as $index => $path) {
            if (is_string($path) && str_starts_with($path, $prefix)) {
                $photos[$index] = route('api.v1.extra-orders.photos.download', [
                    $execution->extra_auftrag_id,
                    $stage,
                    $index,
                ]);
            }
        }

        return $photos;
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
