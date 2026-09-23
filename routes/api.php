<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ExtraAuftragController as ApiExtraAuftragController;
use App\Http\Controllers\Api\V1\FixObjectController as ApiFixObjectController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\ProfileController;
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
    });
});
