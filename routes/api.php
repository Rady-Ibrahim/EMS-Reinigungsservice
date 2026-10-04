<?php

use App\Http\Controllers\Api\V1\AppointmentController as ApiAppointmentController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\CalendarController as ApiCalendarController;
use App\Http\Controllers\Api\V1\ComplaintController;
use App\Http\Controllers\Api\V1\ExtraAuftragController as ApiExtraAuftragController;
use App\Http\Controllers\Api\V1\FixObjectController as ApiFixObjectController;
use App\Http\Controllers\Api\V1\GpsTrackingController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\LocationDocumentController as ApiLocationDocumentController;
use App\Http\Controllers\Api\V1\MonthlyReportController as ApiMonthlyReportController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderReopenController;
use App\Http\Controllers\Api\V1\PreferenceController;
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
        Route::post('/login', [AuthController::class, 'login'])
            ->name('login')
            ->middleware('throttle:5,1');
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

        // In-app preferences (language, password) — the "Me" settings screen
        Route::get('/me/preferences', [PreferenceController::class, 'show'])->name('me.preferences.show');
        Route::put('/me/preferences', [PreferenceController::class, 'update'])->name('me.preferences.update');

        // Locations (read-only for mobile)
        Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
        Route::get('/locations/{location}', [LocationController::class, 'show'])->name('locations.show');

        // Location documents (extern visibility only for field staff)
        Route::get('/locations/{location}/documents', [ApiLocationDocumentController::class, 'index'])
            ->name('locations.documents.index');
        Route::get('/locations/{location}/documents/{document}/download', [ApiLocationDocumentController::class, 'download'])
            ->name('locations.documents.download');

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
        Route::post('/extra-orders/{extraAuftrag}/work-end', [ApiExtraAuftragController::class, 'endWork'])->name('extra-orders.work-end');
        Route::post('/extra-orders/{extraAuftrag}/pause', [ApiExtraAuftragController::class, 'togglePause'])->name('extra-orders.pause');
        Route::post('/extra-orders/{extraAuftrag}/notes', [ApiExtraAuftragController::class, 'addNote'])->name('extra-orders.notes');
        Route::post('/extra-orders/{extraAuftrag}/complete', [ApiExtraAuftragController::class, 'completeOrder'])->name('extra-orders.complete');

        // `auftraege` aliases named in the client specification. `extra-orders`
        // stays the canonical prefix used by the rest of the API.
        Route::get('/auftraege', [ApiExtraAuftragController::class, 'index'])->name('auftraege.index');
        Route::get('/auftraege/{extraAuftrag}', [ApiExtraAuftragController::class, 'show'])->name('auftraege.show');
        Route::post('/auftraege/{extraAuftrag}/work-start', [ApiExtraAuftragController::class, 'startWork'])->name('auftraege.work-start');
        Route::post('/auftraege/{extraAuftrag}/work-end', [ApiExtraAuftragController::class, 'endWork'])->name('auftraege.work-end');
        Route::post('/auftraege/{extraAuftrag}/pause', [ApiExtraAuftragController::class, 'togglePause'])->name('auftraege.pause');
        Route::post('/auftraege/{extraAuftrag}/notes', [ApiExtraAuftragController::class, 'addNote'])->name('auftraege.notes');
        Route::post('/auftraege/{extraAuftrag}/photos', [ApiExtraAuftragController::class, 'uploadOrderPhotos'])->name('auftraege.photos');
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
        // `/calendar` is the canonical endpoint; `/calendar/events` is the
        // alias named in the client specification — both are supported.
        Route::get('/calendar', [ApiCalendarController::class, 'index'])->name('calendar.index');
        Route::get('/calendar/events', [ApiCalendarController::class, 'index'])->name('calendar.events');

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

        // ── Reopen a closed order (governance: reason is mandatory) ─────────
        Route::post('/orders/{order}/reopen', [OrderReopenController::class, 'reopen'])->name('orders.reopen');

        // ── Notifications & push devices ─────────────────────────────────────
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

        Route::post('/device-tokens', [NotificationController::class, 'storeDeviceToken'])->name('device-tokens.store');
        Route::delete('/device-tokens/{deviceToken}', [NotificationController::class, 'destroyDeviceToken'])->name('device-tokens.destroy');

        // Singular alias named in the client specification.
        Route::post('/device-token', [NotificationController::class, 'storeDeviceToken'])->name('device-token.store');

        // ── Complaints / Reklamationen ─────────────────────────────────────
        Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints.index');
        Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');
        Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
        Route::put('/complaints/{complaint}/status', [ComplaintController::class, 'updateStatus'])->name('complaints.status.update');

        // ── Monthly reports (own) ────────────────────────────────────────────
        Route::get('/monthly-reports', [ApiMonthlyReportController::class, 'show'])->name('monthly-reports.show');
        Route::get('/monthly-reports/line-items', [ApiMonthlyReportController::class, 'lineItems'])->name('monthly-reports.line-items');
    });
});
