<?php

namespace App\Providers;

use App\Models\EmployeeShift;
use App\Models\ExtraAuftrag;
use App\Models\FixObjectSchedule;
use App\Models\InternalEvent;
use App\Models\PersonalAppointment;
use App\Models\User;
use App\Observers\TeamupEntityObserver;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        User::observe(UserObserver::class);

        // Queue calendar entities for Teamup sync on every mutation
        EmployeeShift::observe(TeamupEntityObserver::class);
        PersonalAppointment::observe(TeamupEntityObserver::class);
        InternalEvent::observe(TeamupEntityObserver::class);
        FixObjectSchedule::observe(TeamupEntityObserver::class);
        ExtraAuftrag::observe(TeamupEntityObserver::class);
    }
}
