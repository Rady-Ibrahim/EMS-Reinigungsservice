<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\EmployeeAvailabilityStatusEnum;
use App\Enums\ShiftStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\EmployeeAvailabilityRequest as AvailabilityRequest;
use App\Services\ShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeAvailabilityRequestController extends Controller
{
    public function index(): View
    {
        return view('admin.availability_requests.index', [
            'pendingRequests' => AvailabilityRequest::pending()
                ->with('user:id,name,company_id')
                ->orderBy('start_at')
                ->paginate(25, ['*'], 'pending_page'),
            'reviewedRequests' => AvailabilityRequest::query()
                ->where('status', '!=', EmployeeAvailabilityStatusEnum::Pending->value)
                ->with(['user:id,name,company_id', 'reviewer:id,name'])
                ->orderByDesc('reviewed_at')
                ->paginate(25, ['*'], 'reviewed_page'),
        ]);
    }

    public function approve(Request $request, AvailabilityRequest $availabilityRequest, ShiftService $shifts): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $availabilityRequest, $shifts): void {
                $availabilityRequest = AvailabilityRequest::query()
                    ->lockForUpdate()
                    ->findOrFail($availabilityRequest->id);

                abort_unless(
                    $availabilityRequest->status === EmployeeAvailabilityStatusEnum::Pending,
                    409
                );

                $employee = $availabilityRequest->user;
                abort_unless($employee && $employee->is_active, 409);

                $shift = $shifts->create([
                    'user_id' => $availabilityRequest->user_id,
                    'title' => __('availability.shift_title', ['name' => $employee->name]),
                    'start_at' => $availabilityRequest->start_at,
                    'end_at' => $availabilityRequest->end_at,
                    'work_hours' => round($availabilityRequest->start_at->diffInMinutes($availabilityRequest->end_at) / 60, 2),
                    'all_day' => false,
                    'notes' => trim($availabilityRequest->address.($availabilityRequest->notes ? "\n".$availabilityRequest->notes : '')),
                    'status' => ShiftStatusEnum::Planned,
                ]);

                $availabilityRequest->update([
                    'status' => EmployeeAvailabilityStatusEnum::Approved,
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'employee_shift_id' => $shift->id,
                ]);
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', __('availability.flash.approved'));
    }

    public function reject(Request $request, AvailabilityRequest $availabilityRequest): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $availabilityRequest, $data): void {
            $availabilityRequest = AvailabilityRequest::query()
                ->lockForUpdate()
                ->findOrFail($availabilityRequest->id);

            abort_unless(
                $availabilityRequest->status === EmployeeAvailabilityStatusEnum::Pending,
                409
            );

            $availabilityRequest->update([
                'status' => EmployeeAvailabilityStatusEnum::Rejected,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'admin_note' => $data['admin_note'] ?? null,
            ]);
        });

        return back()->with('success', __('availability.flash.rejected'));
    }
}
