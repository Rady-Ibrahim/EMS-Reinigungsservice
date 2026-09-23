<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run on the 1st of every month at 01:00 to pre-generate next month's schedules
Schedule::command('ems:generate-schedules')->monthlyOn(1, '01:00');
