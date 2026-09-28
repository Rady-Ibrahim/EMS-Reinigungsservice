<?php

namespace Tests\Feature\Admin;

use App\Enums\ExtraExecutionStatusEnum;
use App\Enums\ScheduleStatusEnum;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragExecution;
use App\Models\FixObject;
use App\Models\FixObjectAssignment;
use App\Models\FixObjectSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks in the sidebar's live employee status.
 *
 * The panel is a read-only overview, but its colour is what an administrator
 * reads first thing in the morning, so every state is pinned here: paused beats
 * working, working beats travelling, and a finished day reads as off duty
 * rather than as "not started".
 */
class LiveStatusPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->administrator()->create(['locale' => 'en']);
        $this->actingAs($this->admin);
    }

    private function employee(): User
    {
        return User::factory()->mitarbeiter()->create(['name' => 'Erika Musterfrau']);
    }

    private function openExecution(User $employee, ExtraExecutionStatusEnum $status, bool $paused = false): void
    {
        ExtraAuftragExecution::create([
            'extra_auftrag_id' => ExtraAuftrag::factory()->create()->id,
            'user_id'          => $employee->id,
            'status'           => $status,
            'work_start'       => now()->subHour(),
            'pause_started_at' => $paused ? now()->subMinutes(5) : null,
            'pause_ended_at'   => null,
        ]);
    }

    private function todaySchedule(User $employee, ScheduleStatusEnum $status): void
    {
        $fixObject = FixObject::factory()->create();

        FixObjectAssignment::create([
            'fix_object_id' => $fixObject->id,
            'user_id'       => $employee->id,
            'assigned_from' => now()->subMonth()->toDateString(),
        ]);

        FixObjectSchedule::factory()->forDate(now()->toDateString())->create([
            'fix_object_id' => $fixObject->id,
            'status'        => $status,
        ]);
    }

    /**
     * The status label rendered next to the employee's name in the panel.
     */
    private function renderedStatus(string $name): ?string
    {
        $body = $this->get('/admin/dashboard')->assertOk()->getContent();

        if (! preg_match(
            '/live-item.*?st-name">'.preg_quote($name, '/').'<\/span>.*?st-label">([^<]+)</s',
            $body,
            $matches
        )) {
            return null;
        }

        return trim($matches[1]);
    }

    public function test_a_paused_execution_reads_as_on_break(): void
    {
        $employee = $this->employee();

        $this->openExecution($employee, ExtraExecutionStatusEnum::Working, paused: true);

        $this->assertSame('On break', $this->renderedStatus($employee->name));
    }

    public function test_a_finished_pause_goes_back_to_on_duty(): void
    {
        $employee = $this->employee();

        ExtraAuftragExecution::create([
            'extra_auftrag_id' => ExtraAuftrag::factory()->create()->id,
            'user_id'          => $employee->id,
            'status'           => ExtraExecutionStatusEnum::Working,
            'work_start'       => now()->subHour(),
            'pause_started_at' => now()->subMinutes(30),
            'pause_ended_at'   => now()->subMinutes(20),
        ]);

        $this->assertSame('On duty', $this->renderedStatus($employee->name));
    }

    public function test_an_active_execution_reads_as_on_duty(): void
    {
        $employee = $this->employee();

        $this->openExecution($employee, ExtraExecutionStatusEnum::Working);

        $this->assertSame('On duty', $this->renderedStatus($employee->name));
    }

    public function test_a_travelling_execution_reads_as_on_the_way(): void
    {
        $employee = $this->employee();

        $this->openExecution($employee, ExtraExecutionStatusEnum::Travelling);

        $this->assertSame('On the way', $this->renderedStatus($employee->name));
    }

    public function test_a_completed_schedule_reads_as_off_duty(): void
    {
        $employee = $this->employee();

        $this->todaySchedule($employee, ScheduleStatusEnum::Completed);

        $this->assertSame('Off duty', $this->renderedStatus($employee->name));
    }

    public function test_a_pending_schedule_reads_as_not_started(): void
    {
        $employee = $this->employee();

        $this->todaySchedule($employee, ScheduleStatusEnum::Pending);

        $this->assertSame('Not started yet', $this->renderedStatus($employee->name));
    }

    public function test_somebody_with_no_trace_today_reads_as_off_duty(): void
    {
        $employee = $this->employee();

        $this->assertSame('Off duty', $this->renderedStatus($employee->name));
    }
}
