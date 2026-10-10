<?php

namespace App\Providers;

use App\Models\ExtraAuftrag;
use App\Models\FixObjectAssignment;
use App\Models\User;
use App\Observers\ExtraAuftragObserver;
use App\Observers\FixObjectAssignmentObserver;
use App\Observers\UserObserver;
use App\Services\Push\PushChannel;
use App\Services\Push\PushChannelRouter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PushChannel::class, PushChannelRouter::class);
    }

    public function boot(): void
    {
        User::observe(UserObserver::class);

        // Phase 7 — per-user notification engine
        ExtraAuftrag::observe(ExtraAuftragObserver::class);
        FixObjectAssignment::observe(FixObjectAssignmentObserver::class);
    }
}
