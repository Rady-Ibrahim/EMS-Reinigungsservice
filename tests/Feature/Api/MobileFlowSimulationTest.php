<?php

namespace Tests\Feature\Api;

use App\Enums\AssigneeRoleEnum;
use App\Enums\ExtraAuftragStatusEnum;
use App\Enums\FileCategoryEnum;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragAssignee;
use App\Models\LocationDocument;
use App\Models\PersonalAppointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * End-to-end walkthrough of the mobile app, exactly in the order the field
 * crew uses it. Every step goes through the real HTTP kernel — no service
 * calls, no mocked guards — so a 422 or 500 anywhere here is a real defect.
 *
 * Each step prints its request line, HTTP status and a trimmed response body,
 * then asserts the expected status. The transcript is the deliverable; the
 * assertions are what make it trustworthy.
 */
class MobileFlowSimulationTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private string $token = '';

    private int $orderId = 0;

    private int $locationId = 0;

    /** @var list<array{step: int, label: string, status: int, ok: bool}> */
    private array $transcript = [];

    private int $step = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // A Vorarbeiter leading one job today, with a key safe and an alarm,
        // so every security confirmation is genuinely required at hand-over.
        $this->leader = User::factory()->vorarbeiter()->create([
            'name'     => 'Ahmed Al-Sayed',
            'email'    => 'ems-1048@ems-reinigungsservice.de',
            'password' => bcrypt('password'),
            'locale'   => 'de',
        ]);

        $order = ExtraAuftrag::factory()->create([
            'title'             => 'Tiefreinigung Büroebene 3',
            'status'            => ExtraAuftragStatusEnum::Assigned,
            'scheduled_date'    => now()->toDateString(),
            'estimated_hours'   => 2.0,
            'has_alarm_system'  => true,
            'has_key_safe'      => true,
            'checklist_template' => [
                ['task' => 'Boden reinigen',  'completed' => false],
                ['task' => 'Fenster putzen',  'completed' => false],
                ['task' => 'Müll entsorgen',  'completed' => false],
            ],
        ]);

        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $this->leader->id,
            'role_in_order'    => AssigneeRoleEnum::Leader,
        ]);

        $this->orderId    = $order->id;
        $this->locationId = $order->location_id;

        // The location sits at a known point so the field steps can send GPS
        // coordinates that genuinely fall inside the geofence.
        $order->location->update(['latitude' => 49.1000, 'longitude' => 8.1000]);

        PersonalAppointment::factory()->create([
            'user_id'  => $this->leader->id,
            'title'    => 'Team-Besprechung',
            'start_at' => now()->setTime(16, 0),
            'end_at'   => now()->setTime(17, 0),
        ]);
    }

    // ── transcript helpers ────────────────────────────────────────────────

    private function step(string $label, string $method, string $uri, array $payload = []): TestResponse
    {
        $this->step++;
        $verb = strtoupper($method);

        $response = $this->withToken($this->token)->json($method, $uri, $payload);

        $body = $response->getContent();
        $this->transcript[] = [
            'step'   => $this->step,
            'label'  => $label,
            'status' => $response->getStatusCode(),
            'ok'     => $response->isSuccessful(),
        ];

        fwrite(STDERR, sprintf(
            "\n── Step %d · %s\n   %s %s\n   → HTTP %d\n%s",
            $this->step,
            $label,
            $verb,
            $uri,
            $response->getStatusCode(),
            $this->pretty($body)
        ));

        return $response;
    }

    /**
     * Trim a response so the transcript stays readable: enough to see the
     * payload shape and any error keys, without dumping every timestamp.
     */
    private function pretty(string $body): string
    {
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return '   ' . mb_substr($body, 0, 400) . "\n";
        }

        $trim = function (array $array, int $depth = 0) use (&$trim) {
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $array[$key] = $depth < 2 ? $trim($value, $depth + 1) : (array_is_list($value) ? count($value) . ' items' : '…');
                } elseif (is_string($value) && mb_strlen($value) > 90) {
                    $array[$key] = mb_substr($value, 0, 90) . '…';
                }
            }

            return $array;
        };

        $out = json_encode($trim($decoded), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return '   ' . str_replace("\n", "\n   ", $out) . "\n";
    }

    private function printSummary(): void
    {
        $failed = array_values(array_filter($this->transcript, fn($t) => $t['status'] >= 400));

        fwrite(STDERR, "\n\n════════ SIMULATION SUMMARY ════════\n");

        foreach ($this->transcript as $t) {
            fwrite(STDERR, sprintf(
                "  %-6s Step %-2d  HTTP %-3d  %s\n",
                $t['ok'] ? 'PASS' : 'FAIL',
                $t['step'],
                $t['status'],
                $t['label']
            ));
        }

        fwrite(STDERR, sprintf(
            "\n  Steps: %d   Passed: %d   HTTP >= 400: %d\n  422 responses: %d   500 responses: %d\n\n",
            count($this->transcript),
            count(array_filter($this->transcript, fn($t) => $t['ok'])),
            count($failed),
            count(array_filter($failed, fn($t) => $t['status'] === 422)),
            count(array_filter($failed, fn($t) => $t['status'] === 500))
        ));
    }

    // ── the walkthrough ───────────────────────────────────────────────────

    public function test_complete_mobile_user_flow(): void
    {
        // ── Scenario 1 · Login & preferences ──────────────────────────────

        $this->token = '';

        $login = $this->step(
            'Scenario 1.1 · Login as field supervisor (POST /api/v1/login)',
            'POST',
            '/api/v1/login',
            ['email' => 'ems-1048@ems-reinigungsservice.de', 'password' => 'password']
        )
            ->assertOk()
            ->assertJsonPath('data.user.role', 'vorarbeiter');

        $this->token = $login->json('data.token');
        $this->assertNotEmpty($this->token);

        $this->step('Scenario 1.2 · Switch display language to Arabic (PUT /api/v1/me/preferences)', 'PUT', '/api/v1/me/preferences', ['locale' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.locale', 'ar')
            ->assertJsonPath('data.changed.0', 'locale');

        $this->assertSame('ar', $this->leader->fresh()->locale);

        $this->step('Scenario 1.3 · Register FCM push token (POST /api/v1/device-token)', 'POST', '/api/v1/device-token', [
            'token'       => 'fcm_simulated_token_' . bin2hex(random_bytes(8)),
            'platform'    => 'android',
            'provider'    => 'fcm',
            'device_name' => 'Samsung Galaxy X Cover',
        ])->assertCreated();

        $this->assertDatabaseCount('device_tokens', 1);

        // ── Scenario 2 · Today's tasks & unified calendar ──────────────────

        $tasks = $this->step('Scenario 2.1 · Today\'s assigned tasks (GET /api/v1/auftraege)', 'GET', '/api/v1/auftraege')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->orderId);

        // A field user must never see price or cost.
        $this->assertArrayNotHasKey('price', $tasks->json('data.0'));
        $this->assertArrayNotHasKey('internal_cost', $tasks->json('data.0'));

        $calendar = $this->step('Scenario 2.2 · Unified calendar incl. personal (GET /api/v1/calendar/events?show_personal=true)', 'GET', '/api/v1/calendar/events?show_personal=true')
            ->assertOk()
            ->assertJsonStructure(['data', 'layers', 'meta' => ['conflict_count', 'from', 'to', 'personal']]);

        // The brand gold has to be in the palette and has to be the colour
        // carried by the Fixobjekt layer. The other layers are intentionally
        // distinct so the mobile legend stays readable.
        $this->assertContains('#D4AF37', array_column($calendar->json('layers'), 'color'));

        // The Fixobjekt layer must be the brand gold, and every event must
        // carry a `layer` that exists in the published legend — otherwise the
        // app cannot map an event back to the toggle it belongs to.
        $legendKeys = array_column($calendar->json('layers'), 'key');

        $this->assertSame('#D4AF37', collect($calendar->json('layers'))->firstWhere('key', 'fix')['color']);

        foreach (collect($calendar->json('data')) as $event) {
            $this->assertContains(
                $event['layer'],
                $legendKeys,
                'Event ' . $event['id'] . ' reports layer "' . $event['layer'] . '", which is not a published legend key'
            );
        }

        $palette = collect($calendar->json('data'))->pluck('color')->unique()->values()->all();
        fwrite(STDERR, "   legend keys: " . implode(', ', $legendKeys) . "\n");
        fwrite(STDERR, "   event layers: " . collect($calendar->json('data'))->pluck('layer')->unique()->implode(', ') . "\n");
        fwrite(STDERR, "   palette in use: " . implode(', ', $palette) . "\n");

        // ── Scenario 3 · Five-step execution workflow ──────────────────────

        $this->step('Scenario 3.1 · Work start inside the geofence (POST /api/v1/auftraege/{id}/work-start)', 'POST', "/api/v1/auftraege/{$this->orderId}/work-start", [
            'gps_work_start_lat' => 49.10005,
            'gps_work_start_lng' => 8.10005,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'working');

        $this->step('Scenario 3.2 · Start unpaid break (POST /api/v1/auftraege/{id}/pause)', 'POST', "/api/v1/auftraege/{$this->orderId}/pause", ['action' => 'start'])
            ->assertOk()
            ->assertJsonPath('data.is_paused', true);

        // Backdate the shift start so there is real work on both sides of the
        // break. Without this the whole span equals the break and the paid
        // minutes come out as a degenerate zero.
        $this->rewindShiftStart(120);

        // Lengthen the running break to 30 minutes. Start and stop happen
        // milliseconds apart in a test, so without this the break would
        // correctly but uselessfully round down to zero minutes.
        $this->rewindPauseStart(30);

        $this->step('Scenario 3.3 · End unpaid break (POST /api/v1/auftraege/{id}/pause)', 'POST', "/api/v1/auftraege/{$this->orderId}/pause", ['action' => 'stop'])
            ->assertOk()
            ->assertJsonPath('data.is_paused', false);

        $this->step('Scenario 3.4 · Upload mandatory before photos (POST /api/v1/auftraege/{id}/photos)', 'POST', "/api/v1/auftraege/{$this->orderId}/photos", [
            'stage'  => 'before',
            'photos' => ['uploads/before-1.jpg', 'uploads/before-2.jpg'],
        ])
            ->assertOk()
            ->assertJsonPath('data.before_photos.0', 'uploads/before-1.jpg');

        $this->step('Scenario 3.5 · Add a field note (POST /api/v1/auftraege/{id}/notes)', 'POST', "/api/v1/auftraege/{$this->orderId}/notes", [
            'note' => 'Schlüsselübergabe am Empfang; Alarmanlage Code im Tresor.',
        ])->assertOk();

        $this->assertStringContainsString('Schlüsselübergabe', (string) $this->execution()->employee_notes);

        $this->step('Scenario 3.6 · Tick the site checklist (PATCH /api/v1/extra-executions/{id}/checklist)', 'PATCH', '/api/v1/extra-executions/' . $this->execution()->id . '/checklist', [
            'checklist_items' => [
                ['task' => 'Boden reinigen', 'completed' => true],
                ['task' => 'Fenster putzen', 'completed' => true],
                ['task' => 'Müll entsorgen', 'completed' => true],
            ],
        ])->assertOk();

        // Extern documents are visible; the intern plan must not leak.
        $this->createDocuments();

        $documents = $this->step('Scenario 3.7 · Site documents, external only (GET /api/v1/locations/{id}/documents?visibility=extern)', 'GET', "/api/v1/locations/{$this->locationId}/documents?visibility=extern")
            ->assertOk();

        $this->assertSame(
            ['extern'],
            $documents->json('data.*.visibility'),
            'Only external documents may be listed to field staff'
        );

        $this->step('Scenario 3.8 · Upload after photos (POST /api/v1/auftraege/{id}/photos)', 'POST', "/api/v1/auftraege/{$this->orderId}/photos", [
            'stage'  => 'after',
            'photos' => ['uploads/after-1.jpg'],
        ])
            ->assertOk()
            ->assertJsonPath('data.after_photos.0', 'uploads/after-1.jpg');

        $this->step('Scenario 3.9 · Report site damage (POST /api/v1/complaints)', 'POST', '/api/v1/complaints', [
            'location_id' => $this->locationId,
            'title'       => 'Fensterscheibe gesprungen',
            'description' => 'Beim Putzen ist die untere Scheibe des Bürofensters gesprungen.',
            'photos'      => ['uploads/schaden-1.jpg'],
        ])->assertCreated();

        $this->assertDatabaseHas('complaints', ['location_id' => $this->locationId]);

        $this->step('Scenario 3.10 · Secure the site and close the job (POST /api/v1/auftraege/{id}/work-end)', 'POST', "/api/v1/auftraege/{$this->orderId}/work-end", [
            'key_returned'    => true,
            'windows_closed'  => true,
            'alarm_activated' => true,
            'shift_note'      => 'تم التنظيف بالكامل وإغلاق جميع الأبواب وتفعيل جهاز الإنذار.',
            'gps_work_end_lat' => 49.10004,
            'gps_work_end_lng' => 8.10006,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.key_returned', true)
            ->assertJsonPath('data.windows_closed', true)
            ->assertJsonPath('data.alarm_activated', true);

        $execution = $this->execution();

        // The break must be deducted from the span, not merely recorded.
        $elapsed   = (int) $execution->work_start->diffInMinutes($execution->work_end, false);
        $pause     = (int) $execution->pause_minutes;
        $work      = (int) $execution->work_minutes;

        $this->assertGreaterThanOrEqual(30, $pause, 'The 30-minute break must be recorded');
        $this->assertGreaterThan(0, $work, 'There must be paid work left after the break is deducted');
        $this->assertSame(
            $elapsed - $pause,
            $work,
            "Paid minutes ({$work}) must be the span ({$elapsed}) minus the break ({$pause})"
        );

        $this->assertDatabaseHas('extra_auftrag_executions', [
            'extra_auftrag_id' => $this->orderId,
            'user_id'          => $this->leader->id,
            'status'           => 'completed',
            'key_returned'     => 1,
            'windows_closed'   => 1,
            'alarm_activated'  => 1,
        ]);

        // A successful hand-over must not leave a blocked-attempt trail.
        $this->assertDatabaseCount('admin_close_attempts', 0);

        $this->printSummary();

        $this->assertSame(
            [],
            array_values(array_filter($this->transcript, fn($t) => $t['status'] >= 400)),
            'The happy path must not produce any 4xx or 5xx response'
        );
    }

    /**
     * Two breaks in one shift must both be deducted.
     *
     * A shift realistically contains more than one break, and a mobile client
     * that toggles pause/start twice would otherwise be refused ("no break is
     * running") or have the first break silently discarded from the paid-time
     * calculation. This walks two full start/stop cycles over the real kernel
     * and checks the total, then that the total reaches the closing screen.
     */
    public function test_two_breaks_in_one_shift_accumulate(): void
    {
        $login = $this->postJson('/api/v1/login', [
            'email'    => 'ems-1048@ems-reinigungsservice.de',
            'password' => 'password',
        ])->assertOk();

        $this->token = $login->json('data.token');

        $this->withToken($this->token)
            ->postJson("/api/v1/auftraege/{$this->orderId}/work-start", [
                'gps_work_start_lat' => 49.10005,
                'gps_work_start_lng' => 8.10005,
            ])->assertOk();

        // ── First break: 30 minutes ────────────────────────────────────────
        $this->withToken($this->token)
            ->postJson("/api/v1/auftraege/{$this->orderId}/pause", ['action' => 'start'])
            ->assertOk()
            ->assertJsonPath('data.is_paused', true);

        $this->rewindPauseStart(30);

        $this->withToken($this->token)
            ->postJson("/api/v1/auftraege/{$this->orderId}/pause", ['action' => 'stop'])
            ->assertOk()
            ->assertJsonPath('data.is_paused', false);

        $this->assertSame(30, (int) $this->execution()->pause_minutes, 'The first break must be recorded');

        // ── Second break: 45 more minutes ──────────────────────────────────
        // The regression: a second `start` left the first `pause_ended_at` in
        // place, so `isPaused()` reported false and the matching `stop` was
        // rejected as "no break is running".
        $this->withToken($this->token)
            ->postJson("/api/v1/auftraege/{$this->orderId}/pause", ['action' => 'start'])
            ->assertOk()
            ->assertJsonPath('data.is_paused', true, 'A second break must be recognised as running');

        $this->rewindPauseStart(45);

        $this->withToken($this->token)
            ->postJson("/api/v1/auftraege/{$this->orderId}/pause", ['action' => 'stop'])
            ->assertOk()
            ->assertJsonPath('data.is_paused', false);

        $this->assertSame(
            75,
            (int) $this->execution()->pause_minutes,
            'Both breaks must accumulate (30 + 45) instead of the second overwriting the first'
        );

        // ── The accumulated total must reach the closing screen ────────────
        // Hand-over is validated against the stored photos and checklist, so
        // they have to be recorded through their own endpoints first.
        $this->withToken($this->token)
            ->postJson("/api/v1/auftraege/{$this->orderId}/photos", [
                'stage'  => 'before',
                'photos' => ['uploads/before-1.jpg'],
            ])->assertOk();

        $this->withToken($this->token)
            ->patchJson("/api/v1/extra-executions/{$this->executionId()}/checklist", [
                'checklist_items' => [
                    ['task' => 'Boden reinigen', 'completed' => true],
                    ['task' => 'Fenster putzen', 'completed' => true],
                    ['task' => 'Müll entsorgen', 'completed' => true],
                ],
            ])->assertOk();

        $this->withToken($this->token)
            ->postJson("/api/v1/auftraege/{$this->orderId}/photos", [
                'stage'  => 'after',
                'photos' => ['uploads/after-1.jpg'],
            ])->assertOk();

        $this->rewindShiftStart(180);

        $close = $this->withToken($this->token)->postJson("/api/v1/auftraege/{$this->orderId}/work-end", [
            'key_returned'     => true,
            'windows_closed'   => true,
            'alarm_activated'  => true,
            'gps_work_end_lat' => 49.10004,
            'gps_work_end_lng' => 8.10006,
        ])->assertOk();

        $this->assertSame(
            75,
            (int) $close->json('data.pause_minutes'),
            'The closing screen must show the total of both breaks'
        );

        $execution = $this->execution();

        $this->assertSame(
            (int) $execution->work_start->diffInMinutes($execution->work_end, false) - 75,
            (int) $execution->work_minutes,
            'Both breaks must be deducted from the paid minutes'
        );
    }

    /**
     * A second crew closing early without the mandatory inputs. These calls
     * are *expected* to fail — the point is that they fail as 422 with a
     * readable, complete error list and leave an auditable trace, not that
     * they succeed.
     */
    public function test_incomplete_hand_over_is_rejected_and_recorded(): void
    {
        $this->token = '';

        $login = $this->postJson('/api/v1/login', [
            'email' => 'ems-1048@ems-reinigungsservice.de',
            'password' => 'password',
        ])->assertOk();

        $this->token = $login->json('data.token');

        $this->withToken($this->token)
            ->postJson("/api/v1/auftraege/{$this->orderId}/work-start", [
                'gps_work_start_lat' => 49.10005,
                'gps_work_start_lng' => 8.10005,
            ])
            ->assertOk();

        $rejected = $this->withToken($this->token)
            ->postJson("/api/v1/auftraege/{$this->orderId}/work-end", [
                'key_returned'   => false,
                'windows_closed' => false,
                'alarm_activated' => false,
                'gps_work_end_lat' => 49.10004,
                'gps_work_end_lng' => 8.10006,
            ]);

        $this->assertSame(422, $rejected->getStatusCode(), 'An incomplete hand-over must be rejected with 422');

        $errors = $rejected->json('errors');
        $this->assertSame(
            ['before_photos', 'after_photos', 'checklist_items', 'key_returned', 'windows_closed', 'alarm_activated'],
            array_keys($errors),
            'Every missing item must be reported at once, not just the first'
        );

        // A refused close has to leave a trace for the admin evaluator.
        $this->assertDatabaseHas('admin_close_attempts', ['extra_auftrag_id' => $this->orderId]);
        // A blocked attempt must not turn into an admin inbox message.
        $this->assertDatabaseCount('admin_notifications', 0);

        // The execution must be untouched, so the crew can finish and retry.
        $this->assertSame('working', $this->execution()->status->value);

        fwrite(STDERR, sprintf(
            "\n── Step %d · Scenario 4.1 · Close with nothing ticked and no hand-over\n   POST /api/v1/auftraege/%d/work-end\n   → HTTP 422 (expected)\n   rejected with: %s\n   blocked-attempt row written, admin_notifications still empty\n",
            ++$this->step,
            $this->orderId,
            implode(', ', array_keys($errors))
        ));

        // Retrying properly must then succeed and close the record.
        $this->withToken($this->token)->postJson("/api/v1/auftraege/{$this->orderId}/photos", [
            'stage' => 'before', 'photos' => ['b1.jpg'],
        ])->assertOk();
        $this->withToken($this->token)->postJson("/api/v1/auftraege/{$this->orderId}/photos", [
            'stage' => 'after', 'photos' => ['a1.jpg'],
        ])->assertOk();
        $this->withToken($this->token)->patchJson('/api/v1/extra-executions/' . $this->execution()->id . '/checklist', [
            'checklist_items' => [
                ['task' => 'Boden reinigen', 'completed' => true],
                ['task' => 'Fenster putzen', 'completed' => true],
                ['task' => 'Müll entsorgen', 'completed' => true],
            ],
        ])->assertOk();
        $this->withToken($this->token)->postJson("/api/v1/auftraege/{$this->orderId}/work-end", [
            'key_returned'    => true,
            'windows_closed'  => true,
            'alarm_activated' => true,
            'shift_note'      => 'Alles geschlossen, Alarmanlage aktiv.',
            'gps_work_end_lat' => 49.10004,
            'gps_work_end_lng' => 8.10006,
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        fwrite(STDERR, "── Scenario 4.2 · Retry after completing the checklist\n   POST /api/v1/auftraege/{id}/work-end\n   → HTTP 200 — order closed\n\n");
    }

    /**
     * Someone else's order, and an intern document, must both stay invisible.
     */
    public function test_field_staff_cannot_reach_foreign_or_intern_data(): void
    {
        $outsider = User::factory()->mitarbeiter()->create(['password' => bcrypt('password')]);

        $token = $this->postJson('/api/v1/login', [
            'email'    => $outsider->email,
            'password' => 'password',
        ])->assertOk()->json('data.token');

        $this->withToken($token)->getJson("/api/v1/auftraege/{$this->orderId}")
            ->assertNotFound();

        $this->createDocuments();

        $listing = $this->withToken($token)
            ->getJson("/api/v1/locations/{$this->locationId}/documents?visibility=extern")
            ->assertOk();

        $this->assertSame(
            ['extern'],
            $listing->json('data.*.visibility'),
            'A non-leader must not be able to pull intern documents'
        );

        $intern = LocationDocument::where('location_id', $this->locationId)
            ->where('visibility', 'intern')
            ->firstOrFail();

        $this->withToken($token)
            ->getJson("/api/v1/locations/{$this->locationId}/documents/{$intern->id}/download")
            ->assertNotFound();

        // Asking for intern visibility must be refused, not quietly ignored.
        $this->withToken($token)
            ->getJson("/api/v1/locations/{$this->locationId}/documents?visibility=intern")
            ->assertForbidden();

        fwrite(STDERR, "\n── Scenario 5 · Authorisation boundaries\n   foreign order detail        → HTTP 404 (indistinguishable from \"no such order\")\n   intern document listing     → excluded from response\n   intern document download    → HTTP 404\n   visibility=intern request   → HTTP 403\n");
    }

    // ── fixtures ──────────────────────────────────────────────────────────

    private function execution()
    {
        return \App\Models\ExtraAuftragExecution::where('extra_auftrag_id', $this->orderId)
            ->where('user_id', $this->leader->id)
            ->firstOrFail();
    }

    private function executionId(): int
    {
        return (int) $this->execution()->id;
    }

    /**
     * Move the shift start backwards so the closing call sees a whole, checkable
     * span instead of the few seconds the test itself takes to run.
     */
    private function rewindShiftStart(int $minutes): void
    {
        $execution = $this->execution();

        $execution->update([
            'work_start' => $execution->work_start->copy()->subMinutes($minutes),
        ]);
    }

    /**
     * Lengthen the currently running break, so stopping it yields a break of a
     * known number of minutes rather than the sub-second gap the test creates.
     */
    private function rewindPauseStart(int $minutes): void
    {
        $execution = $this->execution();

        $this->assertNotNull($execution->pause_started_at, 'A break must be running before it can be lengthened');

        $execution->update([
            'pause_started_at' => $execution->pause_started_at->copy()->subMinutes($minutes),
        ]);
    }

    private function createDocuments(): void
    {
        LocationDocument::factory()->extern()->category(FileCategoryEnum::Instructions)->create([
            'location_id' => $this->locationId,
            'title'       => 'Objektschlüssel-Übergabe',
        ]);

        LocationDocument::factory()->intern()->create([
            'location_id' => $this->locationId,
            'title'       => 'Interner Kostenvoranschlag',
        ]);
    }

}
