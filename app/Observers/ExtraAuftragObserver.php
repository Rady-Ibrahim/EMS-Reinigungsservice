<?php

namespace App\Observers;

use App\Enums\ExtraAuftragStatusEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\ExtraAuftrag;
use App\Services\NotificationEngineService;

class ExtraAuftragObserver
{
    public function __construct(
        private readonly NotificationEngineService $notifications
    ) {
    }

    public function created(ExtraAuftrag $order): void
    {
        if (! $order->relationLoaded('assignees')) {
            $order->load('assignees');
        }

        $employeeIds = $order->assignees->pluck('user_id')->all();

        if (empty($employeeIds)) {
            return;
        }

        $this->notifications->sendToEmployees(
            $employeeIds,
            NotificationTypeEnum::JobAssigned,
            'Neuer Auftrag zugewiesen',
            "Du wurdest für \"{$order->title}\" am {$order->scheduled_date->format('d.m.Y')} eingeteilt.",
            ['type' => 'extra', 'auftrag_id' => $order->id]
        );
    }

    public function updated(ExtraAuftrag $order): void
    {
        if ($order->wasChanged('status')
            && $order->status === ExtraAuftragStatusEnum::Cancelled
            && $order->getOriginal('status') !== ExtraAuftragStatusEnum::Cancelled->value) {
            if (! $order->relationLoaded('assignees')) {
                $order->load('assignees');
            }

            $employeeIds = $order->assignees->pluck('user_id')->all();

            if (empty($employeeIds)) {
                return;
            }

            $message = 'Der Auftrag "'.$order->title.'" wurde storniert.';
            if ($order->cancelled_reason) {
                $message .= ' Grund: '.$order->cancelled_reason;
            }

            $this->notifications->sendToEmployees(
                $employeeIds,
                NotificationTypeEnum::JobCancelled,
                'Auftrag storniert',
                $message,
                ['type' => 'extra', 'auftrag_id' => $order->id]
            );
        }
    }
}