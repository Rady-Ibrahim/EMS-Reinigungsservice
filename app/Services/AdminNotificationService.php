<?php

namespace App\Services;

use App\Enums\AdminNotificationTypeEnum;
use App\Models\AdminNotification;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminNotificationService
{
    public function notify(
        AdminNotificationTypeEnum $type,
        string $title,
        ?string $message = null,
        array $payload = [],
        ?string $dedupeKey = null
    ): AdminNotification {
        return AdminNotification::create([
            'type'       => $type,
            'title'      => $title,
            'message'    => $message,
            'payload'    => empty($payload) ? null : $payload,
            'dedupe_key' => $dedupeKey,
        ]);
    }

    public function unreadCount(): int
    {
        return AdminNotification::unread()->count();
    }

    public function list(int $perPage = 20): LengthAwarePaginator
    {
        return AdminNotification::latest()->paginate($perPage);
    }

    public function markRead(AdminNotification $notification): AdminNotification
    {
        $notification->update(['is_read' => true]);

        return $notification;
    }

    public function markAllRead(): void
    {
        AdminNotification::unread()->update(['is_read' => true]);
    }
}