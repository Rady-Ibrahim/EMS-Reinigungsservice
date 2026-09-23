<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run on the 1st of every month at 01:00 to pre-generate next month's schedules
Schedule::command('ems:generate-schedules')->monthlyOn(1, '01:00');

// Teamup sync — flush pending pushes and pull remote changes every 5 minutes
Schedule::command('ems:teamup-sync')->everyFiveMinutes();

// Smart admin alerts — hourly: missing photos + overdue orders
Schedule::command('ems:admin-alerts', ['--photos' => true, '--unclosed' => true])->hourly();

// Smart admin alerts — daily 02:00: monthly-hours coverage
Schedule::command('ems:admin-alerts', ['--monthly-hours' => true])->dailyAt('02:00');
