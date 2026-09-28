<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExtraAuftrag;
use App\Models\FixObjectSchedule;
use App\Services\OrderReopenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reopening a closed work order from outside the dashboard.
 *
 * POST /api/v1/orders/{order}/reopen
 *
 * The `order` route parameter accepts either an Extra-Auftrag or a Fixobjekt
 * schedule — prefix it with `extra-` or `schedule-` to be explicit:
 *
 *     POST /api/v1/orders/extra-42/reopen
 *     POST /api/v1/orders/schedule-17/reopen
 *     POST /api/v1/orders/42/reopen      (resolves to an Extra-Auftrag)
 *
 * A reason is mandatory and is written to the audit log together with the
 * actor and the originating IP address.
 */
class OrderReopenController extends Controller
{
    public function __construct(private readonly OrderReopenService $reopen)
    {
    }

    public function reopen(Request $request, string $order): JsonResponse
    {
        $actor = $request->user();

        // Reopening is a supervisory act — Vorarbeiter may not do it.
        abort_unless($actor->isAdministrator() || $actor->isVorarbeiter(), Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [], [
            'reason' => __('validation.reason'),
        ]);

        $reason = $validated['reason'];

        if (str_starts_with($order, 'schedule-')) {
            $schedule = FixObjectSchedule::find((int) substr($order, 9));

            abort_if($schedule === null, Response::HTTP_NOT_FOUND);

            $result = $this->reopen->reopenFix($schedule, $actor, $reason);

            return response()->json([
                'message' => __('messages.order.reopened'),
                'data'    => [
                    'type'      => 'fix_schedule',
                    'id'        => $result->id,
                    'status'    => $result->status->value,
                ],
            ], Response::HTTP_OK);
        }

        $id       = str_starts_with($order, 'extra-') ? (int) substr($order, 6) : (int) $order;
        $extraOrder = ExtraAuftrag::find($id);

        abort_if($extraOrder === null, Response::HTTP_NOT_FOUND);

        $result = $this->reopen->reopenExtra($extraOrder, $actor, $reason);

        return response()->json([
            'message' => __('messages.order.reopened'),
            'data'    => [
                'type'   => 'extra_auftrag',
                'id'     => $result->id,
                'status' => $result->status->value,
            ],
        ], Response::HTTP_OK);
    }
}
