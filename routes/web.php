<?php

use App\Http\Controllers\Web\Admin\Auth\LoginController;
use App\Http\Controllers\Web\Admin\CustomerController;
use App\Http\Controllers\Web\Admin\CustomerLocationController;
use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\Admin\EmployeeController;
use App\Http\Controllers\Web\Admin\ExtraAuftragController;
use App\Http\Controllers\Web\Admin\FixObjectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Admin Dashboard (Session-based)
|--------------------------------------------------------------------------
*/

// ── Guest routes ─────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/', fn() => redirect()->route('admin.login'));
    Route::get('/admin/login', [LoginController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/admin/login', [LoginController::class, 'login'])->name('admin.login.post');
});

// Alias so Laravel's built-in auth middleware redirect works
Route::get('/login', fn() => redirect()->route('admin.login'))->name('login');

// ── Authenticated Admin routes ────────────────────────────────────────────
Route::middleware(['auth', 'role:administrator'])->prefix('admin')->name('admin.')->group(function () {

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Customers ────────────────────────────────────────────────────────
    Route::resource('customers', CustomerController::class);
    Route::patch('customers/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])
         ->name('customers.toggle-status');

    // ── Customer Locations (nested) ───────────────────────────────────────
    Route::resource('customers.locations', CustomerLocationController::class)
         ->shallow(); // generates non-nested routes for show/edit/update/destroy

    // ── Employees ─────────────────────────────────────────────────────────
    Route::resource('employees', EmployeeController::class);

    // ── Fix Objects ────────────────────────────────────────────────────────
    Route::resource('fix-objects', FixObjectController::class);
    Route::post('fix-objects/{fixObject}/assignments', [FixObjectController::class, 'storeAssignment'])
         ->name('fix-objects.assignments.store');
    Route::delete('fix-objects/{fixObject}/assignments/{assignment}', [FixObjectController::class, 'destroyAssignment'])
         ->name('fix-objects.assignments.destroy');
    Route::post('fix-objects/{fixObject}/generate-schedules', [FixObjectController::class, 'generateSchedules'])
         ->name('fix-objects.generate-schedules');

    // ── Extra-Aufträge ────────────────────────────────────────────────────
    Route::resource('extra-auftraege', ExtraAuftragController::class)
         ->except(['destroy']);
    Route::post('extra-auftraege/{extraAuftrag}/cancel', [ExtraAuftragController::class, 'cancel'])
         ->name('extra-auftraege.cancel');
});
