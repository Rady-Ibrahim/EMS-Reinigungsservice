<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEmployeeAvailabilityRequest as StoreRequest;
use App\Models\EmployeeAvailabilityRequest as AvailabilityRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmployeeAvailabilityRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeEmployee($request);

        $requests = AvailabilityRequest::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(25)
            ->through(fn (AvailabilityRequest $availability) => $this->format($availability));

        return response()->json($requests);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorizeEmployee($request);

        $availability = AvailabilityRequest::create(
            $request->validated() + ['user_id' => $request->user()->id]
        );

        return response()->json(['data' => $this->format($availability)], Response::HTTP_CREATED);
    }

    private function authorizeEmployee(Request $request): void
    {
        abort_unless($request->user()->isApiUser(), Response::HTTP_FORBIDDEN);
    }

    private function format(AvailabilityRequest $availability): array
    {
        return [
            'id' => $availability->id,
            'start_at' => $availability->start_at->toIso8601String(),
            'end_at' => $availability->end_at->toIso8601String(),
            'address' => $availability->address,
            'notes' => $availability->notes,
            'status' => $availability->status->value,
            'admin_note' => $availability->admin_note,
            'shift_id' => $availability->employee_shift_id,
            'created_at' => $availability->created_at->toIso8601String(),
        ];
    }
}
