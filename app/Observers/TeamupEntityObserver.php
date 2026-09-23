<?php

namespace App\Observers;

use App\Services\TeamupSyncService;
use Illuminate\Database\Eloquent\Model;

/**
 * Queues every calendar-entity mutation for the next Teamup sync flush.
 */
class TeamupEntityObserver
{
    public function created(Model $entity): void
    {
        app(TeamupSyncService::class)->markPending($entity);
    }

    public function updated(Model $entity): void
    {
        app(TeamupSyncService::class)->markPending($entity);
    }

    public function deleted(Model $entity): void
    {
        app(TeamupSyncService::class)->markPending($entity);
    }
}