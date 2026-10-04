<?php

namespace App\Notifications;

use App\Enums\NotificationTypeEnum;
use App\Models\ExtraAuftrag;
use App\Notifications\Channels\OneSignalDeviceChannel;
use App\Notifications\Contracts\PushableNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * "You have been assigned to a job" — sent over OneSignal to every active
 * Player ID the assignee registered via POST /api/v1/device-tokens.
 *
 * ExtraAuftragObserver::created() still notifies through
 * NotificationEngineService, which reaches the same devices via
 * PushChannelRouter. Use this class when you want the standard Laravel
 * `Notification::send($user, new JobAssignedNotification($order))` ergonomics
 * — e.g. from a console command or a queued job — without opening a second
 * delivery path.
 */
class JobAssignedNotification extends Notification implements PushableNotification
{
    use Queueable;

    public function __construct(
        private readonly ExtraAuftrag $order,
    ) {}

    /**
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        return [OneSignalDeviceChannel::class];
    }

    /**
     * Payload for the in-app feed / database channel.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => NotificationTypeEnum::JobAssigned->value,
            'order_id' => $this->order->id,
            'title' => 'Neuer Auftrag zugewiesen',
            'scheduled_on' => $this->order->scheduled_date?->format('d.m.Y'),
        ];
    }

    /**
     * @return array{title: string, body: string, data: array}
     */
    public function toPushMessage(): array
    {
        return [
            'title' => 'Neuer Auftrag zugewiesen',
            'body' => sprintf(
                'Du wurdest für "%s" am %s eingeteilt.',
                $this->order->title,
                $this->order->scheduled_date?->format('d.m.Y') ?? '—',
            ),
            'data' => [
                'type' => NotificationTypeEnum::JobAssigned->value,
                'auftrag_id' => $this->order->id,
            ],
        ];
    }
}
