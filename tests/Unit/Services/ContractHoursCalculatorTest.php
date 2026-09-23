<?php

namespace Tests\Unit\Services;

use App\Enums\FixFrequencyEnum;
use App\Services\ContractHoursCalculator;
use Database\Factories\FixObjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractHoursCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private ContractHoursCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ContractHoursCalculator();
    }

    // ── Weekly (3x/week Mon,Wed,Fri) ───────────────────────────────────────

    public function test_triweekly_mon_wed_fri_october_2026(): void
    {
        // October 2026 actual count: Mon=4, Wed=4, Fri=5 → 13 occurrences
        $fo = \App\Models\FixObject::factory()->create([
            'frequency'      => FixFrequencyEnum::Triweekly,
            'frequency_days' => ['Mon', 'Wed', 'Fri'],
            'contract_hours' => 2.5,
            'valid_from'     => '2026-01-01',
            'valid_until'    => null,
        ]);

        $hours = $this->calculator->monthlyHours($fo, 2026, 10);
        $count = $this->calculator->countOccurrences($fo, 2026, 10);

        $this->assertEquals(13, $count);
        $this->assertEquals(32.5, $hours); // 13 × 2.5
    }

    // ── Weekly (1x/week, Monday only) ─────────────────────────────────────

    public function test_weekly_monday_only_october_2026(): void
    {
        // October 2026 has exactly 4 Mondays (Oct 5, 12, 19, 26)
        $fo = \App\Models\FixObject::factory()->create([
            'frequency'      => FixFrequencyEnum::Weekly,
            'frequency_days' => ['Mon'],
            'contract_hours' => 3.0,
            'valid_from'     => '2026-01-01',
        ]);

        $count = $this->calculator->countOccurrences($fo, 2026, 10);
        $this->assertEquals(4, $count);
        $this->assertEquals(12.0, $this->calculator->monthlyHours($fo, 2026, 10));
    }

    // ── Daily (Mon–Fri) ────────────────────────────────────────────────────

    public function test_daily_weekdays_october_2026(): void
    {
        // October 2026: 22 working days (Mon–Fri only)
        $fo = \App\Models\FixObject::factory()->create([
            'frequency'      => FixFrequencyEnum::Daily,
            'frequency_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'contract_hours' => 1.0,
            'valid_from'     => '2026-01-01',
        ]);

        $count = $this->calculator->countOccurrences($fo, 2026, 10);
        $this->assertEquals(22, $count);
        $this->assertEquals(22.0, $this->calculator->monthlyHours($fo, 2026, 10));
    }

    // ── Monthly ────────────────────────────────────────────────────────────

    public function test_monthly_frequency_has_exactly_one_occurrence(): void
    {
        $fo = \App\Models\FixObject::factory()->monthly()->create([
            'contract_hours' => 4.0,
            'valid_from'     => '2026-10-01',
        ]);

        $count = $this->calculator->countOccurrences($fo, 2026, 10);
        $this->assertEquals(1, $count);
        $this->assertEquals(4.0, $this->calculator->monthlyHours($fo, 2026, 10));
    }

    // ── Contract validity clipping ─────────────────────────────────────────

    public function test_hours_are_zero_before_contract_start(): void
    {
        $fo = \App\Models\FixObject::factory()->create([
            'frequency'      => FixFrequencyEnum::Weekly,
            'frequency_days' => ['Mon'],
            'contract_hours' => 2.0,
            'valid_from'     => '2026-11-01', // starts November
        ]);

        $hours = $this->calculator->monthlyHours($fo, 2026, 10); // October
        $this->assertEquals(0.0, $hours);
    }

    public function test_hours_are_zero_after_contract_end(): void
    {
        $fo = \App\Models\FixObject::factory()->create([
            'frequency'      => FixFrequencyEnum::Weekly,
            'frequency_days' => ['Mon'],
            'contract_hours' => 2.0,
            'valid_from'     => '2026-01-01',
            'valid_until'    => '2026-09-30', // ended September
        ]);

        $hours = $this->calculator->monthlyHours($fo, 2026, 10); // October
        $this->assertEquals(0.0, $hours);
    }

    public function test_partial_month_clips_to_validity(): void
    {
        // Contract starts Oct 15 — only 2 Mondays left in Oct 2026 (Oct 19 and 26)
        $fo = \App\Models\FixObject::factory()->create([
            'frequency'      => FixFrequencyEnum::Weekly,
            'frequency_days' => ['Mon'],
            'contract_hours' => 3.0,
            'valid_from'     => '2026-10-15',
        ]);

        $count = $this->calculator->countOccurrences($fo, 2026, 10);
        $this->assertEquals(2, $count);
        $this->assertEquals(6.0, $this->calculator->monthlyHours($fo, 2026, 10));
    }

    // ── Annual calculation ──────────────────────────────────────────────────

    public function test_annual_hours_sum_across_12_months(): void
    {
        $fo = \App\Models\FixObject::factory()->create([
            'frequency'      => FixFrequencyEnum::Monthly,
            'frequency_days' => [],
            'contract_hours' => 4.0,
            'valid_from'     => '2026-01-01',
        ]);

        $annual = $this->calculator->annualHours($fo, 2026);
        $this->assertEquals(48.0, $annual); // 12 months × 4h
    }

    // ── contract_hours_applied is frozen at execution ─────────────────────

    public function test_contract_hours_applied_matches_fix_object_hours(): void
    {
        $fo       = \App\Models\FixObject::factory()->withContractHours(2.5)->create();
        $schedule = \App\Models\FixObjectSchedule::factory()->create(['fix_object_id' => $fo->id]);
        $user     = \App\Models\User::factory()->mitarbeiter()->create();

        $assignment = \App\Models\FixObjectAssignment::create([
            'fix_object_id' => $fo->id,
            'user_id'       => $user->id,
            'assigned_from' => now()->subDay()->toDateString(),
        ]);

        $service   = app(\App\Services\FixObjectService::class);
        $execution = $service->startExecution($schedule, $user->id, []);

        $this->assertEquals('2.50', $execution->contract_hours_applied);

        // Simulate contract change — applied hours must NOT change
        $fo->update(['contract_hours' => 4.0]);
        $execution->refresh();
        $this->assertEquals('2.50', $execution->contract_hours_applied, 'Frozen hours must not change after contract update');
    }
}
