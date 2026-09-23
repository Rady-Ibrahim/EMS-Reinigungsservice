<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\UserNotification;
use App\Services\NotificationEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationEngineService $notifications
    ) {
    }

    /**
     * Register / update this device's FCM push token for the logged-in user.
     */
    public function storeDeviceToken(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan('notifications:manage'), Response::HTTP_FORBIDDEN);

        $validation = Validator::make($request->all(), [
            'token'       => ['required', 'string', 'max:2048'],
            'platform'    => ['required', 'in:android,ios,web'],
            'provider'    => ['sometimes', 'in:fcm,webpush'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        if ($validation->fails()) {
            return response()->json(['message' => 'Validierungsfehler', 'errors' => $validation->errors()], 422);
        }

        $max = (int) config('push.max_devices_per_user', 5);

        $deletedOld = DeviceToken::where('user_id', $request->user()->id)
            ->where('token', $request->input('token'))
            ->delete();

        $alreadyStored = DeviceToken::forUser($request->user()->id)->count();

        if ($alreadyStored >= $max) {
            DeviceToken::where('user_id', $request->user()->id)
                ->orderBy('last_used_at', 'asc')
                ->limit($alreadyStored - $max + 1)
                ->delete();
        }

        $device = DeviceToken::create([
            'user_id'     => $request->user()->id,
            'platform'    => $request->input('platform'),
            'provider'    => $request->input('provider', 'fcm'),
            'token'       => $request->input('token'),
            'device_name' => $request->input('device_name'),
            'last_used_at'=> now(),
            'is_active'   => true,
        ]);

        return response()->json([
            'message'    => 'Gerät registriert.',
            'device'     => $device->only('id', 'platform', 'provider', 'device_name'),
            'max_devices'=> $max,
        ], 201);
    }

    /**
     * Remove the supplied device tokens (e.g. on logout or app uninstall).
     */
    public function destroyDeviceToken(Request $request, DeviceToken $deviceToken): JsonResponse
    {
        abort_unless($request->user()->tokenCan('notifications:manage'), Response::HTTP_FORBIDDEN);

        if ($deviceToken->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Nicht gefunden.'], 404);
        }

        $deviceToken->delete();

        return response()->json(['message' => 'Gerät entfernt.']);
    }

    /**
     * The logged-in user's in-app notifications (latest first).
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan('notifications:manage'), Response::HTTP_FORBIDDEN);

        $validation = Validator::make($request->all(), [
            'type'    => ['sometimes', 'nullable', 'string', 'max:60'],
            'unread'  => ['sometimes', 'boolean'],
            'from'    => ['sometimes', 'nullable', 'date'],
            'to'      => ['sometimes', 'nullable', 'date'],
            'per_page'=> ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validation->fails()) {
            return response()->json(['message' => 'Validierungsfehler', 'errors' => $validation->errors()], 422);
        }

        $page = $this->notifications->listForUser(
            $request->user(),
            [
                'type'   => $request->input('type'),
                'unread' => $request->boolean('unread'),
                'from'   => $request->input('from'),
                'to'     => $request->input('to'),
            ],
            (int) ($request->input('per_page') ?: 20)
        );

        return response()->json([
            'data'       => $page->items(),
            'unread'     => $this->notifications->unreadCountFor($request->user()),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($request->user()->tokenCan('notifications:manage'), Response::HTTP_FORBIDDEN);

        $notification = $this->notifications->markRead($notification, $request->user());

        return response()->json([
            'message'  => 'Als gelesen markiert.',
            'read_at'  => $notification->read_at?->toISOString(),
        ]);
    }

    /**
     * Mark all of the user's notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan('notifications:manage'), Response::HTTP_FORBIDDEN);

        $updated = $this->notifications->markAllRead($request->user());

        return response()->json([
            'message'   => 'Alle als gelesen markiert.',
            'updated'   => $updated,
        ]);
    }
}