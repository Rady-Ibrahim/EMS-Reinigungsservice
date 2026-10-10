<?php

use App\Http\Controllers\Web\Admin\Auth\LoginController;
use App\Http\Controllers\Web\Admin\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Web\Admin\AppointmentController;
use App\Http\Controllers\Web\Admin\AuditLogController;
use App\Http\Controllers\Web\Admin\CalendarController;
use App\Http\Controllers\Web\Admin\ComplaintController;
use App\Http\Controllers\Web\Admin\CustomerController;
use App\Http\Controllers\Web\Admin\CustomerLocationController;
use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\Admin\EmployeeController;
use App\Http\Controllers\Web\Admin\ExtraAuftragController;
use App\Http\Controllers\Web\Admin\FixObjectController;
use App\Http\Controllers\Web\Admin\InternalEventController;
use App\Http\Controllers\Web\Admin\LocaleController;
use App\Http\Controllers\Web\Admin\LocationDocumentController;
use App\Http\Controllers\Web\Admin\MonthlyReportController;
use App\Http\Controllers\Web\Admin\NotificationController;
use App\Http\Controllers\Web\Admin\ReassignmentController;
use App\Http\Controllers\Web\Admin\ReopenController;
use App\Http\Controllers\Web\Admin\ShiftController;
use App\Http\Controllers\Web\Admin\TimeAdjustmentController;
use App\Http\Controllers\Web\Admin\TwoFactorController;
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

    // Second login step — TOTP challenge (2FA)
    Route::get('/admin/2fa/challenge', [TwoFactorChallengeController::class, 'showChallenge'])->name('admin.2fa.challenge');
    Route::post('/admin/2fa/challenge', [TwoFactorChallengeController::class, 'verify'])->name('admin.2fa.challenge.post');
});

// Alias so Laravel's built-in auth middleware redirect works
Route::get('/login', fn() => redirect()->route('admin.login'))->name('login');

// Language switch — accessible for guests too (login page toggle)
Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

// ── Authenticated Admin routes ────────────────────────────────────────────
Route::middleware(['auth', 'role:administrator'])->prefix('admin')->name('admin.')->group(function () {

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

    // ── Customers ────────────────────────────────────────────────────────
    Route::resource('customers', CustomerController::class);
    Route::patch('customers/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])
         ->name('customers.toggle-status');

    // ── Customer Locations (nested) ───────────────────────────────────────
    Route::resource('customers.locations', CustomerLocationController::class)
         ->shallow(); // generates non-nested routes for show/edit/update/destroy

    // ── Location Documents (Objekt-Dokumenten) ────────────────────────────
    Route::prefix('locations/{location}/documents')->name('locations.documents.')->group(function () {
        Route::get('/', [LocationDocumentController::class, 'index'])->name('index');
        Route::post('/', [LocationDocumentController::class, 'store'])->name('store');
        Route::get('/{document}/download', [LocationDocumentController::class, 'download'])->name('download');
        Route::patch('/{document}', [LocationDocumentController::class, 'update'])->name('update');
        Route::delete('/{document}', [LocationDocumentController::class, 'destroy'])->name('destroy');
    });

    // ── Complaints / Reklamationen ────────────────────────────────────────
    Route::get('complaints', [ComplaintController::class, 'index'])->name('complaints.index');
    Route::get('complaints/create', [ComplaintController::class, 'create'])->name('complaints.create');
    Route::post('complaints', [ComplaintController::class, 'store'])->name('complaints.store');
    Route::get('complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
    Route::patch('complaints/{complaint}/status', [ComplaintController::class, 'updateStatus'])->name('complaints.status.update');
    Route::patch('complaints/{complaint}/assign', [ComplaintController::class, 'assign'])->name('complaints.assign');

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
    Route::get('extra-auftraege', [ExtraAuftragController::class, 'index'])
         ->name('extra-auftraege.index');
    Route::get('extra-auftraege/create', [ExtraAuftragController::class, 'create'])
         ->name('extra-auftraege.create');
    Route::post('extra-auftraege', [ExtraAuftragController::class, 'store'])
         ->name('extra-auftraege.store');
    Route::get('extra-auftraege/{extraAuftrag}', [ExtraAuftragController::class, 'show'])
         ->name('extra-auftraege.show');
    Route::get('extra-auftraege/{extraAuftrag}/edit', [ExtraAuftragController::class, 'edit'])
         ->name('extra-auftraege.edit');
    Route::put('extra-auftraege/{extraAuftrag}', [ExtraAuftragController::class, 'update'])
         ->name('extra-auftraege.update');
    Route::post('extra-auftraege/{extraAuftrag}/cancel', [ExtraAuftragController::class, 'cancel'])
         ->name('extra-auftraege.cancel');
    Route::post('extra-auftraege/{extraAuftrag}/reopen', [ReopenController::class, 'reopenExtra'])
         ->name('extra-auftraege.reopen');

    // ── Time Adjustments ──────────────────────────────────────────────────
    Route::get('time-adjustments', [TimeAdjustmentController::class, 'index'])
         ->name('time-adjustments.index');
    Route::get('time-adjustments/{timeAdjustment}', [TimeAdjustmentController::class, 'show'])
         ->name('time-adjustments.show');
    Route::post('time-adjustments/{timeAdjustment}/approve', [TimeAdjustmentController::class, 'approve'])
         ->name('time-adjustments.approve');
    Route::post('time-adjustments/{timeAdjustment}/reject', [TimeAdjustmentController::class, 'reject'])
         ->name('time-adjustments.reject');

    // ── Time Tracking Overview ─────────────────────────────────────────────
    Route::get('time-tracking', [TimeAdjustmentController::class, 'trackingOverview'])
         ->name('time-tracking.overview');

    // ── Monthly Reports (Payroll) ──────────────────────────────────────────
    Route::prefix('monthly-reports')->name('monthly-reports.')->group(function () {
        Route::get('/', [MonthlyReportController::class, 'index'])->name('index');
        Route::get('discrepancies', [MonthlyReportController::class, 'discrepancies'])
             ->name('discrepancies');
        Route::get('{monthlyReport}', [MonthlyReportController::class, 'show'])->name('show');
        Route::post('{monthlyReport}/approve', [MonthlyReportController::class, 'approve'])->name('approve');
        Route::get('{monthlyReport}/export-pdf', [MonthlyReportController::class, 'exportPdf'])
             ->name('export-pdf');
    });

    // ── Payroll Excel export (single route, outside resource nesting) ──────
    Route::get('monthly-reports-export', [MonthlyReportController::class, 'exportExcel'])
         ->name('monthly-reports.export-excel');

    // ── Admin Notifications ────────────────────────────────────────────────
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('{notification}', [NotificationController::class, 'show'])->name('show');
        Route::post('{notification}/read', [NotificationController::class, 'markRead'])->name('read');
        Route::post('{userNotification}/mark-user-read', [NotificationController::class, 'markUserRead'])->name('user-read');
        Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
    });

    // ── Audit Log (Governance) ─────────────────────────────────────────────
    Route::prefix('audit-log')->name('audit-log.')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
        Route::get('{auditLog}', [AuditLogController::class, 'show'])->name('show');
    });

    // ── Two-Factor Authentication (setup) ──────────────────────────────────
    Route::prefix('two-factor')->name('two-factor.')->group(function () {
        Route::get('/', [TwoFactorController::class, 'setup'])->name('setup');
        Route::post('/confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
        Route::post('/disable', [TwoFactorController::class, 'disable'])->name('disable');
        Route::get('/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('recovery-codes');
    });

    // ── Interactive Calendar ───────────────────────────────────────────────
    Route::prefix('calendar')->name('calendar.')->group(function () {
        Route::get('/', [CalendarController::class, 'index'])->name('index');
        Route::get('/events', [CalendarController::class, 'events'])->name('events');
        Route::get('/layers', [CalendarController::class, 'layers'])->name('layers');

        // Shifts
        Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
        Route::get('/shifts/create', [ShiftController::class, 'create'])->name('shifts.create');
        Route::post('/shifts', [ShiftController::class, 'store'])->name('shifts.store');
        Route::get('/shifts/{shift}/edit', [ShiftController::class, 'edit'])->name('shifts.edit');
        Route::put('/shifts/{shift}', [ShiftController::class, 'update'])->name('shifts.update');
        Route::delete('/shifts/{shift}', [ShiftController::class, 'destroy'])->name('shifts.destroy');
        Route::post('/shifts/{shift}/cancel', [ShiftController::class, 'cancel'])->name('shifts.cancel');

        // Personal appointments
        Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/appointments/create', [AppointmentController::class, 'create'])->name('appointments.create');
        Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::get('/appointments/{appointment}/edit', [AppointmentController::class, 'edit'])->name('appointments.edit');
        Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
        Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');

        // Internal events
        Route::get('/internal-events', [InternalEventController::class, 'index'])->name('internal-events.index');
        Route::get('/internal-events/create', [InternalEventController::class, 'create'])->name('internal-events.create');
        Route::post('/internal-events', [InternalEventController::class, 'store'])->name('internal-events.store');
        Route::get('/internal-events/{event}/edit', [InternalEventController::class, 'edit'])->name('internal-events.edit');
        Route::put('/internal-events/{event}', [InternalEventController::class, 'update'])->name('internal-events.update');
        Route::delete('/internal-events/{event}', [InternalEventController::class, 'destroy'])->name('internal-events.destroy');
    });

    // ── Dynamic Reassignment ───────────────────────────────────────────────
    Route::get('fix-objects/{fixObject}/reassign', [ReassignmentController::class, 'createContract'])
         ->name('fix-objects.reassign');
    Route::post('fix-objects/{fixObject}/reassign', [ReassignmentController::class, 'storeContract'])
         ->name('fix-objects.reassign.store');
    Route::get('schedules/{schedule}/reassign', [ReassignmentController::class, 'createSchedule'])
         ->name('calendar.schedules.reassign');
    Route::post('schedules/{schedule}/reassign', [ReassignmentController::class, 'storeSchedule'])
         ->name('calendar.schedules.reassign.store');
    Route::post('schedules/{schedule}/reopen', [ReopenController::class, 'reopenSchedule'])
         ->name('calendar.schedules.reopen');
    Route::get('extra-auftraege/assignees/{assignee}/reassign', [ReassignmentController::class, 'createExtraAssignee'])
         ->name('extra-auftraege.assignees.reassign');
    Route::post('extra-auftraege/assignees/{assignee}/reassign', [ReassignmentController::class, 'storeExtraAssignee'])
         ->name('extra-auftraege.assignees.reassign.store');
});
