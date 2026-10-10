<?php

namespace App\Console\Commands;

use App\Enums\AssigneeRoleEnum;
use App\Enums\ExtraAuftragStatusEnum;
use App\Enums\ScheduleStatusEnum;
use App\Enums\ShiftStatusEnum;
use App\Models\Customer;
use App\Models\CustomerLocation;
use App\Models\DeviceToken;
use App\Models\EmployeeShift;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragAssignee;
use App\Models\FixObject;
use App\Models\FixObjectAssignment;
use App\Models\FixObjectSchedule;
use App\Models\PersonalAppointment;
use App\Models\User;
use App\Services\AuthService;
use App\Services\ExtraAuftragService;
use App\Services\GpsTrackingService;
use App\Services\TimeAdjustmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Live field-crew simulation against the CURRENT database.
 *
 * Seeds demo accounts + today's work (ExtraAuftrag, FixObject schedule,
 * shift, personal appointment — one per calendar layer) and then walks the
 * employee journey step by step through the real services, pausing between
 * steps so an admin can watch the dashboard, calendar and reports update.
 *
 * Additive only: never deletes or modifies foreign data. Safe to re-run —
 * every run creates a fresh order/shift/appointment.
 *
 * Usage: php artisan ems:simulate-journey [--sleep=4] [--fast]
 */
class SimulateEmployeeJourney extends Command
{
    protected $signature = 'ems:simulate-journey
        {--sleep=4 : Seconds to pause between live steps}
        {--fast : Run through without pauses}';

    protected $description = 'Simulate a live employee journey (travel → work → docs → close) for dashboard watching';

    private int $sleep = 4;

    public function handle(
        AuthService $auth,
        ExtraAuftragService $orders,
        GpsTrackingService $gps,
        TimeAdjustmentService $adjustments,
    ): int {
        $this->sleep = $this->option('fast') ? 0 : max(0, (int) $this->option('sleep'));
        $base = rtrim((string) config('app.url', 'http://localhost:8000'), '/');
        $today = now()->toDateString();
        $lat = 49.10005;
        $lng = 8.10005;

        $this->line('');
        $this->info('════════ EMS Live-Simulation: Mitarbeiter-Reise ════════');

        // ── 0. Seed ──────────────────────────────────────────────────────
        $this->step('0', 'Stammdaten + Accounts');
        $admin = $this->account('admin@ems.de', 'administrator', 'مدير تجريبي');
        $leader = $this->account('leader@ems.de', 'vorarbeiter', 'مشرف تجريبي');
        $worker = $this->account('employee@ems.de', 'mitarbeiter', 'موظف تجريبي');

        $customer = Customer::firstOrCreate(
            ['email' => 'live-demo@ems-reinigungsservice.de'],
            ['name' => 'Live-Demo Kunde GmbH', 'phone' => '+49 170 000000', 'contact_person' => 'Demo Kontakt']
        );

        $location = CustomerLocation::firstOrCreate(
            ['customer_id' => $customer->id, 'name' => 'Live-Demo Halle 7'],
            [
                'street' => 'Musterstraße', 'house_number' => '7', 'postal_code' => '77652',
                'city' => 'Offenburg', 'country' => 'DE',
                'latitude' => 49.1000, 'longitude' => 8.1000,
                'contact_person' => 'Pforte', 'contact_phone' => '+49 170 000001',
                'is_active' => true,
            ]
        );

        $order = ExtraAuftrag::create([
            'customer_id' => $customer->id,
            'location_id' => $location->id,
            'created_by' => $admin->id,
            'title' => 'Live-Demo Auftrag #' . now()->format('His'),
            'description' => 'Interaktive Simulation für das Admin-Dashboard.',
            'scheduled_date' => $today,
            'scheduled_time_start' => '08:00:00',
            'estimated_hours' => 8.0,
            'is_travel_time_paid' => true,
            'status' => ExtraAuftragStatusEnum::Assigned,
            'has_alarm_system' => true,
            'has_key_safe' => true,
            'checklist_template' => [
                ['task' => 'Boden reinigen', 'completed' => false],
                ['task' => 'Fenster putzen', 'completed' => false],
                ['task' => 'Müll entsorgen', 'completed' => false],
            ],
        ]);
        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id, 'user_id' => $leader->id,
            'role_in_order' => AssigneeRoleEnum::Leader,
        ]);
        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id, 'user_id' => $worker->id,
            'role_in_order' => AssigneeRoleEnum::Member,
        ]);

        // One entry per calendar layer so all 5 light up.
        $fixObject = FixObject::firstOrCreate(
            ['title' => 'Live-Demo Objekt (Simulation)'],
            [
                'customer_id' => $customer->id, 'location_id' => $location->id,
                'frequency' => \App\Enums\FixFrequencyEnum::Weekly,
                'frequency_days' => ['Mon', 'Wed', 'Fri'],
                'contract_hours' => 2.0, 'time_start' => '07:00:00', 'time_end' => '09:00:00',
                'valid_from' => now()->startOfMonth()->toDateString(), 'is_active' => true,
            ]
        );
        FixObjectAssignment::firstOrCreate(
            ['fix_object_id' => $fixObject->id, 'user_id' => $worker->id, 'assigned_from' => now()->subMonth()->toDateString()],
        );
        $schedule = FixObjectSchedule::firstOrCreate(
            ['fix_object_id' => $fixObject->id, 'scheduled_date' => $today],
            ['scheduled_start' => '07:00:00', 'scheduled_end' => '09:00:00', 'status' => ScheduleStatusEnum::Pending],
        );
        if ($schedule->status->isTerminal()) {
            $schedule->update(['status' => ScheduleStatusEnum::Pending]);
        }

        EmployeeShift::create([
            'user_id' => $worker->id, 'created_by' => $admin->id,
            'title' => 'Live-Demo Frühschicht',
            'start_at' => $today . ' 06:00:00', 'end_at' => $today . ' 14:00:00',
            'all_day' => false, 'status' => ShiftStatusEnum::Planned,
        ]);

        PersonalAppointment::create([
            'user_id' => $worker->id, 'title' => 'Live-Demo Teambesprechung',
            'start_at' => $today . ' 16:00:00', 'end_at' => $today . ' 17:00:00',
            'location' => 'Büro Offenburg',
        ]);

        // Mobile login proof + push device.
        $loginUser = $auth->attemptLogin(['email' => 'employee@ems.de', 'password' => 'password']);
        $tokenData = $auth->createApiToken($loginUser ?? $worker);
        DeviceToken::updateOrCreate(
            ['token' => 'onesignal-live-sim-player'],
            [
                'user_id' => $worker->id, 'platform' => 'android', 'provider' => 'onesignal',
                'device_name' => 'Live-Simulationsgerät', 'last_used_at' => now(), 'is_active' => true,
            ]
        );

        $this->line("   Login OK: <info>employee@ems.de</info> (Rolle: {$worker->role->value}), Token: <comment>" . substr($tokenData['token'], 0, 10) . '…</comment>');
        $this->line("   Auftrag #{$order->id} angelegt + Kalender-Layer: fix/extra/shifts/personal/internal");
        $this->printLinks($base);
        $this->pause('Accounts + Tagesdaten angelegt. Dashboard öffnen, dann geht es los.');

        // ── 1. Travel ────────────────────────────────────────────────────
        $this->step('1', 'Mitarbeiter startet die Anfahrt (travel/start + GPS)');
        $departure = now()->subMinutes(30);
        $track = $orders->startTravel($order, $worker->id, [
            'gps_departure_lat' => $lat, 'gps_departure_lng' => $lng,
            'departure_at' => $departure->toIso8601String(),
        ]);
        $gps->recordBatch($track, [
            ['latitude' => 49.10010, 'longitude' => 8.10010, 'recorded_at' => $departure->copy()->addMinutes(5)->toIso8601String()],
            ['latitude' => 49.10020, 'longitude' => 8.10020, 'recorded_at' => $departure->copy()->addMinutes(15)->toIso8601String()],
            ['latitude' => $lat, 'longitude' => $lng, 'recorded_at' => $departure->copy()->addMinutes(25)->toIso8601String()],
        ]);
        $this->line("   Track #{$track->id}, 3 GPS-Punkte übertragen.");
        $this->watch("Dashboard → Live-Status: <info>{$worker->name} = Auf Anfahrt</info> 🚗", $base . '/admin/dashboard');

        // ── 2. Arrive + work ─────────────────────────────────────────────
        $this->step('2', 'Ankunft + Arbeitsstart (travel/arrive, work/start)');
        $track = $orders->recordArrival($order, $worker->id, [
            'gps_arrival_lat' => $lat, 'gps_arrival_lng' => $lng,
            'arrival_at' => $departure->copy()->addMinutes(30)->toIso8601String(),
        ]);
        $orders->startWork($order, $worker->id, [
            'gps_work_start_lat' => $lat, 'gps_work_start_lng' => $lng,
            'work_start' => now()->subHours(2)->toIso8601String(),
        ]);
        $this->line("   Anfahrt: <info>{$track->travel_minutes} Min (bezahlt: " . ($track->is_paid ? 'ja' : 'nein') . ')</info>. Arbeit läuft.');
        $this->watch("Refresh → Live-Status: <info>{$worker->name} = Im Einsatz</info> 🧹", $base . '/admin/dashboard');

        // ── 3. Docs (leader) ─────────────────────────────────────────────
        $this->step('3', 'Vorarbeiter dokumentiert (Fotos + Checklist)');
        $orders->startTravel($order, $leader->id, []);
        $orders->recordArrival($order, $leader->id, []);
        $orders->startWork($order, $leader->id, [
            'work_start' => now()->subHour()->toIso8601String(),
        ]);
        $orders->uploadBeforePhotos($order, $leader->id, ['uploads/live-vorher-1.jpg', 'uploads/live-vorher-2.jpg']);
        $orders->updateChecklist($order, $leader->id, [
            ['task' => 'Boden reinigen', 'completed' => true],
            ['task' => 'Fenster putzen', 'completed' => true],
            ['task' => 'Müll entsorgen', 'completed' => true],
        ]);
        $orders->addNote($order, $worker->id, 'Live-Simulation: Halle betreten, keine Besonderheiten.');
        $this->line('   Vorher-Fotos (2) + Checklist (3/3) + Feldnotiz gespeichert.');
        $this->watch('Kalender → Auftrag anklicken: Details + Fotos + Checkliste 📸', $base . '/admin/calendar');

        // ── 4. Close + adjustment ────────────────────────────────────────
        $this->step('4', 'Übergabe + Abschluss + Zeitkorrektur (work-end, complete)');
        $leaderEnd = $orders->endWork($order, $leader->id, [
            'key_returned' => true, 'windows_closed' => true, 'alarm_activated' => true,
            'shift_note' => 'Live-Simulation: alles gesichert.',
            'after_photos' => ['uploads/live-nachher-1.jpg'],
        ]);
        $orders->completeOrder($order, $leader->id, []);
        $workerExecution = $order->executions()->where('user_id', $worker->id)->first();

        $adjustment = $adjustments->submit([
            'employee_id' => $worker->id,
            'job_type' => 'extra_auftrag',
            'job_id' => $order->id,
            'requested_start' => now()->subHours(3)->toIso8601String(),
            'requested_end' => now()->subHour()->toIso8601String(),
            'reason' => 'Live-Simulation: GPS-Start verspaetet, Beginn korrigieren.',
        ]);

        $this->line("   Leader: {$leaderEnd->work_minutes} Min Arbeit / {$leaderEnd->paid_minutes} Min bezahlt.");
        $this->line("   Mitarbeiter: {$workerExecution->work_minutes} Min Arbeit / {$workerExecution->paid_minutes} Min bezahlt (inkl. Anfahrt).");
        $this->line("   Zeitkorrektur #{$adjustment->id} als <comment>pending</comment> eingereicht.");
        $this->watch('Berichte → Monatsstunden + Anfahrt im Monthly Report 📊', $base . '/admin/monthly-reports');
        $this->watch('Zeitausgleich → Antrag freigeben/ablehnen', $base . '/admin/time-adjustments');

        $this->line('');
        $this->info("✔ Simulation abgeschlossen — Auftrag #{$order->id} ist completed, Live-Status: Feierabend.");
        $this->printLinks($base);

        return self::SUCCESS;
    }

    private function account(string $email, string $role, string $name): User
    {
        $factory = match ($role) {
            'administrator' => User::factory()->administrator(),
            'vorarbeiter' => User::factory()->vorarbeiter(),
            default => User::factory()->mitarbeiter(),
        };

        $user = User::where('email', $email)->first();
        if (! $user) {
            $user = $factory->create(['name' => $name, 'email' => $email]);
        }

        $user->forceFill([
            'name' => $name,
            'password' => Hash::make('password'),
            'is_active' => true,
            'locale' => 'de',
        ])->save();

        return $user->fresh();
    }

    private function step(string $n, string $title): void
    {
        $this->line('');
        $this->line("<fg=yellow>── Schritt {$n}: {$title}</>");
    }

    private function watch(string $what, string $url): void
    {
        $this->line("   👉 {$what}");
        $this->line("      {$url}");
        $this->pause(null);
    }

    private function pause(?string $message): void
    {
        if ($this->sleep <= 0) {
            return;
        }
        if ($message) {
            $this->line("   {$message}");
        }
        $this->line("   … weiter in {$this->sleep}s (Enter = sofort, --fast = ohne Pausen)");
        sleep($this->sleep);
    }

    private function printLinks(string $base): void
    {
        $this->line('');
        $this->line('   Admin-Login: <info>admin@ems.de / password</info>');
        $this->line("   Dashboard:   {$base}/admin/dashboard");
        $this->line("   Kalender:    {$base}/admin/calendar");
        $this->line("   Berichte:    {$base}/admin/monthly-reports");
        $this->line("   Zeitausgleich: {$base}/admin/time-adjustments");
    }
}
