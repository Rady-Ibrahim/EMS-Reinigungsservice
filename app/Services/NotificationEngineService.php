<?php

namespace App\Services;

use App\Enums\AdminNotificationTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\RoleEnum;
use App\Models\AdminNotification;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Push\PushChannel;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Central notification engine for Phase 7.
 *
 * Two complementary sinks:
 *  - `notifications` (per-user): mobile in-app feed + admin notification center.
 *  - `admin_notifications`: global admin inbox (conflicts, Teamup, alerts).
 *
 * Push delivery goes through PushChannel and is a silent no-op whenever FCM
 * is not configured — database delivery always happens.
 */
class NotificationEngineService
{
    public function __construct(
        private readonly PushChannel $push,
        private readonly AdminNotificationService $adminInbox
    ) {
    }

    // ── Per-user delivery (mobile + web) ───────────────────────────────────

    public function sendToUser(
        User $user,
        NotificationTypeEnum|string $type,
        string $title,
        ?string $message = null,
        array $payload = [],
        array $channels = ['database', 'push']
    ): ?UserNotification {
        $typeValue = $type instanceof NotificationTypeEnum ? $type->value : $type;

        $row = null;

        if (in_array('database', $channels, true)) {
            $row = UserNotification::create([
                'user_id' => $user->id,
                'type'    => $typeValue,
                'title'   => $title,
                'message' => $message,
                'payload' => empty($payload) ? null : $payload,
            ]);
        }

        if (in_array('push', $channels, true)) {
            $this->pushToUser($user, $title, $message, $typeValue, $payload);
        }

        return $row;
    }

    /**
     * Fan out the same notification to every user with a given role.
     */
    public function sendToRole(
        RoleEnum $role,
        NotificationTypeEnum|string $type,
        string $title,
        ?string $message = null,
        array $payload = [],
        array $channels = ['database', 'push']
    ): int {
        $users = User::where('role', $role->value)->where('is_active', true)->get();

        foreach ($users as $user) {
            $this->sendToUser($user, $type, $title, $message, $payload, $channels);
        }

        return $users->count();
    }

    /**
     * Notify all employees with a given role for a job event, scoped to the
     * actual affected employees (e.g. the assigned team only).
     *
     * @param  iterable<int>  $userIds
     */
    public function sendToEmployees(
        iterable $userIds,
        NotificationTypeEnum|string $type,
        string $title,
        ?string $message = null,
        array $payload = [],
        array $channels = ['database', 'push']
    ): int {
        $count = 0;

        foreach ($userIds as $userId) {
            $user = User::find($userId);

            if (! $user || ! $user->is_active) {
                continue;
            }

            $this->sendToUser($user, $type, $title, $message, $payload, $channels);
            $count++;
        }

        return $count;
    }

    // ── Admin global inbox ─────────────────────────────────────────────────

    public function notifyAdmins(
        AdminNotificationTypeEnum $type,
        string $title,
        ?string $message = null,
        array $payload = [],
        ?string $dedupeKey = null
    ): AdminNotification {
        return $this->adminInbox->notify($type, $title, $message, $payload, $dedupeKey);
    }

    // ── Per-user queries (mobile API) ──────────────────────────────────────

    public function listForUser(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = UserNotification::query()->forUser($user->id);

        if ($filters['type'] ?? null) {
            $query->ofType($filters['type']);
        }
        if (($filters['unread'] ?? false)) {
            $query->unread();
        }
        if ($filters['from'] ?? null) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if ($filters['to'] ?? null) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function unreadCountFor(User $user): int
    {
        return UserNotification::forUser($user->id)->unread()->count();
    }

    public function markRead(UserNotification $notification, User $user): UserNotification
    {
        if ($notification->user_id !== $user->id) {
            abort(403, 'Not your notification.');
        }
        $notification->markAsRead();

        return $notification;
    }

    public function markAllRead(User $user): int
    {
        return UserNotification::forUser($user->id)->unread()->update(['read_at' => now()]);
    }

    // ── Private ────────────────────────────────────────────────────────────

    private function pushToUser(User $user, string $title, ?string $message, string $type, array $payload): void
    {
        $tokens = $user->deviceTokens()
            ->active()
            ->limit((int) config('push.max_devices_per_user', 5))
            ->get();

        foreach ($tokens as $token) {
            $this->push->send($token, [
                'title' => $title,
                'body'  => $message ?? '',
                'data'  => array_merge(['type' => $type], $payload),
            ]);
        }
    }
}