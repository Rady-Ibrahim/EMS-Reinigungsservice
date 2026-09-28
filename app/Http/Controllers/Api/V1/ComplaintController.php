<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ComplaintStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Services\ComplaintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Complaints (Reklamationen) for the mobile app.
 *
 * Response envelope follows the house standard: `data` for the payload,
 * `meta` for pagination, `message` as a translation KEY on writes.
 */
class ComplaintController extends Controller
{
    public function __construct(private readonly ComplaintService $complaints)
    {
    }

    /**
     * GET /api/v1/complaints
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(ComplaintService::filterRules());

        $paginator = $this->complaints->listFor(
            $request->user(),
            $filters,
            (int) ($filters['per_page'] ?? 15),
        );

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn(Complaint $c) => $this->format($c))
                ->values(),
            'meta' => [
                'total'    => $paginator->total(),
                'page'     => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/v1/complaints
     */
    public function store(Request $request): JsonResponse
    {
        $attributes = $request->validate(ComplaintService::storeRules());

        $complaint = $this->complaints->create($attributes, $request->user());

        return response()->json([
            'data'    => $this->format($complaint->fresh(['location', 'customer', 'assignedEmployee'])),
            'message' => 'messages.complaints.created',
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/v1/complaints/{complaint}
     */
    public function show(Request $request, Complaint $complaint): JsonResponse
    {
        $complaint = $this->complaints->findVisible($complaint, $request->user());

        return response()->json([
            'data' => $this->format($complaint->loadMissing(['location', 'customer', 'assignedEmployee', 'resolver'])),
        ], Response::HTTP_OK);
    }

    /**
     * PUT /api/v1/complaints/{complaint}/status
     */
    public function updateStatus(Request $request, Complaint $complaint): JsonResponse
    {
        // Resolving a complaint is an office decision, not a field one.
        abort_unless(
            $request->user()->isAdministrator() || $request->user()->isVorarbeiter(),
            Response::HTTP_FORBIDDEN,
            'messages.complaints.status_forbidden'
        );

        $complaint = $this->complaints->findVisible($complaint, $request->user());

        $attributes = $request->validate(ComplaintService::statusRules());

        $status = ComplaintStatusEnum::from($attributes['status']);

        // A resolution without a note is not a resolution.
        if ($status === ComplaintStatusEnum::Resolved && blank($attributes['resolution'] ?? null)) {
            return response()->json([
                'message' => 'messages.complaints.resolution_required',
                'errors'  => ['resolution' => [__('validation.resolution_required')]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $complaint = $this->complaints->updateStatus($complaint, $status, $attributes, $request->user());

        return response()->json([
            'data'    => $this->format($complaint),
            'message' => 'messages.complaints.status_updated',
        ], Response::HTTP_OK);
    }

    /**
     * The wire format of a complaint.
     *
     * @return array<string, mixed>
     */
    private function format(Complaint $complaint): array
    {
        return [
            'id'                   => $complaint->id,
            'client_id'            => $complaint->customer_id,
            'location_id'          => $complaint->location_id,
            'assigned_employee_id' => $complaint->assigned_employee_id,
            'title'                => $complaint->title,
            'description'          => $complaint->description,
            'status'               => $complaint->status->value,
            'photos'               => $complaint->photos ?? [],
            'resolution'           => $complaint->resolution,
            'resolved_at'          => $complaint->resolved_at?->toIso8601String(),
            'created_at'           => $complaint->created_at?->toIso8601String(),
            'updated_at'           => $complaint->updated_at?->toIso8601String(),
        ];
    }
}
