<?php

namespace Tests\Unit\Services;

use App\Enums\TeamupSyncStatusEnum;
use App\Models\PersonalAppointment;
use App\Models\EmployeeShift;
use App\Models\InternalEvent;
use App\Models\TeamupSetting;
use App\Models\TeamupSyncState;
use App\Models\FixObjectSchedule;
use App\Models\ExtraAuftrag;
use App\Models\User;
use App\Services\TeamupSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeamupSyncTest extends TestCase
{
    use RefreshDatabase;

    private TeamupSyncService $sync;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sync = app(TeamupSyncService::class);
    }

    private function configureTeamup(array $subcalendars = ['default' => 1001]): void
    {
        TeamupSetting::create([
            'calendar_key'    => 'ks123456',
            'api_key'         => 'teamup_api_key',
            'enabled'         => true,
            'subcalendar_ids' => $subcalendars,
            'timezone'        => 'Europe/Berlin',
        ]);
    }

    public function test_mark_pending_is_noop_when_not_configured(): void
    {
        $shift = EmployeeShift::factory()->create();

        $this->sync->markPending($shift);

        $this->assertCount(0, TeamupSyncState::all());
    }

    public function test_mark_pending_creates_state_when_configured(): void
    {
        $this->configureTeamup();

        $shift = EmployeeShift::factory()->create();

        $this->sync->markPending($shift);

        $state = TeamupSyncState::where('entity_type', 'employee_shift')->where('entity_id', $shift->id)->first();
        $this->assertNotNull($state);
        $this->assertEquals(TeamupSyncStatusEnum::Pending, $state->status);
    }

    public function test_payload_for_personal_appointment(): void
    {
        $this->configureTeamup(['personal_appointment' => 2002]);

        $appointment = PersonalAppointment::create([
            'user_id'  => User::factory()->mitarbeiter()->create()->id,
            'title'    => 'Zahnarzt',
            'start_at' => '2026-10-01 09:30:00',
            'end_at'   => '2026-10-01 10:30:00',
            'location' => 'Praxisklinik',
        ]);

        $payload = $this->sync->payloadFor($appointment);

        $this->assertEquals(2002, $payload['subcalendar_id']);
        $this->assertEquals('Zahnarzt', $payload['title']);
        // Times are localized to the calendar timezone (Europe/Berlin, UTC+2 in Oct)
        $this->assertSame('2026-10-01T11:30:00', $payload['start_dt']);
        $this->assertSame('2026-10-01T12:30:00', $payload['end_dt']);
        $this->assertFalse($payload['all_day']);
        $this->assertEquals('Praxisklinik', $payload['location']);
    }

    public function test_payload_for_all_day_internal_event_uses_exclusive_end(): void
    {
        $this->configureTeamup(['internal_event' => 3003]);

        $event = InternalEvent::factory()->state([
            'created_by' => User::factory()->administrator()->create()->id,
            'all_day'    => true,
            'start_at'   => '2026-10-05 00:00:00',
            'end_at'     => '2026-10-05 23:59:59',
        ])->create();

        $payload = $this->sync->payloadFor($event);

        $this->assertSame('2026-10-05', $payload['start_dt']);
        $this->assertSame('2026-10-06', $payload['end_dt']); // Teamup exclusive end
        $this->assertTrue($payload['all_day']);
    }

    public function test_payload_for_fix_schedule_sets_contract_hours_note(): void
    {
        $this->configureTeamup(['fix_schedule' => 4004]);

        $fix = \App\Models\FixObject::factory()->withContractHours(2.5)->create();
        $schedule = FixObjectSchedule::factory()->forDate('2026-10-07')->create([
            'fix_object_id'   => $fix->id,
            'scheduled_start' => '07:00:00',
            'scheduled_end'   => '09:00:00',
        ]);

        $payload = $this->sync->payloadFor($schedule);

        $this->assertSame('2026-10-07T09:00:00', $payload['start_dt']);
        $this->assertSame('2026-10-07T11:00:00', $payload['end_dt']);
        $this->assertStringContainsString('2.5', $payload['notes']);
    }

    public function test_sync_all_pushes_pending_and_adopts_remote_id(): void
    {
        $this->configureTeamup();

        Http::fake([
            'https://api.teamup.com/*' => Http::response(['event' => ['id' => 'remote-1']], 200),
        ]);

        $shift = EmployeeShift::factory()->create();
        $this->sync->markPending($shift);

        $summary = $this->sync->syncAll();

        $state = $shift->fresh();
        $this->assertEquals(TeamupSyncStatusEnum::Synced, TeamupSyncState::where('entity_type', 'employee_shift')->where('entity_id', $shift->id)->first()->status);
        $this->assertSame('remote-1', TeamupSyncState::where('entity_type', 'employee_shift')->where('entity_id', $shift->id)->first()->teamup_event_id);
        $this->assertArrayHasKey('pushed', $summary);
        $this->assertEquals(1, $summary['pushed']);
    }

    public function test_sync_all_returns_disabled_when_not_configured(): void
    {
        $summary = $this->sync->syncAll();

        $this->assertSame(['enabled' => false], $summary);
    }

    public function test_extra_order_payload_without_time_is_all_day(): void
    {
        $this->configureTeamup(['extra_auftrag' => 5005]);

        $order = ExtraAuftrag::factory()->create([
            'scheduled_date'       => '2026-10-10',
            'scheduled_time_start' => null,
        ]);

        $payload = $this->sync->payloadFor($order);

        $this->assertSame('2026-10-10', $payload['start_dt']);
        $this->assertSame('2026-10-11', $payload['end_dt']);
        $this->assertTrue($payload['all_day']);
    }
}