<?php

namespace App\Observers;

use App\Enums\NotificationTypeEnum;
use App\Models\FixObjectAssignment;
use App\Services\NotificationEngineService;

class FixObjectAssignmentObserver
{
    public function __construct(
        private readonly NotificationEngineService $notifications
    ) {
    }

    public function created(FixObjectAssignment $assignment): void
    {
        $user = $assignment->user;

        if (! $user || ! $user->is_active) {
            return;
        }

        $fix = $assignment->fixObject;

        $this->notifications->sendToUser(
            $user,
            NotificationTypeEnum::JobAssigned,
            'Neues Fixobjekt zugewiesen',
            $fix ? "Du wurdest dem Objekt \"{$fix->name}\" zugeteilt." : 'Es wurde dir ein Fixobjekt zugeteilt.',
            ['type' => 'fix', 'fix_object_id' => $assignment->fix_object_id]
        );
    }
}