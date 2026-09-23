<?php

use App\Http\Controllers\Api\V1\AppointmentController as ApiAppointmentController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\CalendarController as ApiCalendarController;
use App\Http\Controllers\Api\V1\ExtraAuftragController as ApiExtraAuftragController;
use App\Http\Controllers\Api\V1\FixObjectController as ApiFixObjectController;
use App\Http\Controllers\Api\V1\GpsTrackingController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReassignmentController as ApiReassignmentController;
use App\Http\Controllers\Api\V1\ShiftController as ApiShiftController;
use App\Http\Controllers\Api\V1\TimeTrackingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Mobile App (Sanctum Token-based)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ── Public (no token required) ────────────────────────────────────────
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
    });

    // ── Protected (valid Sanctum token required) ──────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('/me', [AuthController::class, 'me'])->name('me');
        });

        // Employee profile
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

        // Locations (read-only for mobile)
        Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
        Route::get('/locations/{location}', [LocationController::class, 'show'])->name('locations.show');

        // Fix Objects
        Route::get('/fix-objects', [ApiFixObjectController::class, 'index'])->name('fix-objects.index');
        Route::get('/fix-objects/{fixObject}', [ApiFixObjectController::class, 'show'])->name('fix-objects.show');
        Route::get('/fix-objects/{fixObject}/schedules', [ApiFixObjectController::class, 'schedules'])->name('fix-objects.schedules');

        // Execution workflow
        Route::post('/schedules/{schedule}/start', [ApiFixObjectController::class, 'startExecution'])->name('schedules.start');
        Route::patch('/executions/{execution}/status', [ApiFixObjectController::class, 'updateStatus'])->name('executions.status');
        Route::post('/executions/{execution}/complete', [ApiFixObjectController::class, 'completeExecution'])->name('executions.complete');

        // ── Extra-Aufträge ────────────────────────────────────────────────
        Route::get('/extra-orders', [ApiExtraAuftragController::class, 'index'])->name('extra-orders.index');
        Route::get('/extra-orders/{extraAuftrag}', [ApiExtraAuftragController::class, 'show'])->name('extra-orders.show');
        Route::post('/extra-orders/{extraAuftrag}/travel/start', [ApiExtraAuftragController::class, 'startTravel'])->name('extra-orders.travel.start');
        Route::post('/extra-orders/{extraAuftrag}/travel/arrive', [ApiExtraAuftragController::class, 'recordArrival'])->name('extra-orders.travel.arrive');
        Route::post('/extra-orders/{extraAuftrag}/work/start', [ApiExtraAuftragController::class, 'startWork'])->name('extra-orders.work.start');
        Route::post('/extra-orders/{extraAuftrag}/complete', [ApiExtraAuftragController::class, 'completeOrder'])->name('extra-orders.complete');
        // Execution-level actions (leader only)
        Route::post('/extra-executions/{execution}/before-photos', [ApiExtraAuftragController::class, 'uploadBeforePhotos'])->name('extra-executions.before-photos');
        Route::patch('/extra-executions/{execution}/checklist', [ApiExtraAuftragController::class, 'updateChecklist'])->name('extra-executions.checklist');

        // ── GPS Tracking ──────────────────────────────────────────────────
        Route::get('/travel-tracks/{travelTrack}/gps', [GpsTrackingController::class, 'index'])->name('gps.index');
        Route::post('/travel-tracks/{travelTrack}/gps', [GpsTrackingController::class, 'store'])->name('gps.store');
        Route::post('/travel-tracks/{travelTrack}/gps/batch', [GpsTrackingController::class, 'storeBatch'])->name('gps.batch');

        // ── Time Tracking ─────────────────────────────────────────────────
        Route::get('/time-summary', [TimeTrackingController::class, 'monthlySummary'])->name('time.summary');
        Route::get('/time-adjustments', [TimeTrackingController::class, 'indexAdjustments'])->name('time-adjustments.index');
        Route::post('/time-adjustments', [TimeTrackingController::class, 'submitAdjustment'])->name('time-adjustments.submit');

        // ── Interactive Calendar (unified) ─────────────────────────────────
        Route::get('/calendar', [ApiCalendarController::class, 'index'])->name('calendar.index');

        // ── Personal appointments (own) ────────────────────────────────────
        Route::get('/appointments', [ApiAppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/appointments', [ApiAppointmentController::class, 'store'])->name('appointments.store');
        Route::patch('/appointments/{appointment}', [ApiAppointmentController::class, 'update'])->name('appointments.update');
        Route::delete('/appointments/{appointment}', [ApiAppointmentController::class, 'destroy'])->name('appointments.destroy');

        // ── Shifts ─────────────────────────────────────────────────────────
        Route::get('/shifts', [ApiShiftController::class, 'index'])->name('shifts.index');
        Route::post('/shifts', [ApiShiftController::class, 'store'])->name('shifts.store');
        Route::delete('/shifts/{shift}', [ApiShiftController::class, 'destroy'])->name('shifts.destroy');

        // ── Dynamic Reassignment (Vorarbeiter) ─────────────────────────────
        Route::post('/schedules/{schedule}/reassign', [ApiReassignmentController::class, 'reassignSchedule'])->name('schedules.reassign');
        Route::post('/extra-assignees/{assignee}/reassign', [ApiReassignmentController::class, 'reassignExtraAssignee'])->name('extra-assignees.reassign');

        // ── Notifications & push devices ─────────────────────────────────────
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

        Route::post('/device-tokens', [NotificationController::class, 'storeDeviceToken'])->name('device-tokens.store');
        Route::delete('/device-tokens/{deviceToken}', [NotificationController::class, 'destroyDeviceToken'])->name('device-tokens.destroy');
    });
});
