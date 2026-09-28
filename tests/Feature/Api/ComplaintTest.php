<?php

namespace Tests\Feature\Api;

use App\Enums\AdminNotificationTypeEnum;
use App\Enums\AuditEventEnum;
use App\Enums\ComplaintStatusEnum;
use App\Models\AdminNotification;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\CustomerLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComplaintTest extends TestCase
{
    use RefreshDatabase;

    // ── Envelope: data + meta ─────────────────────────────────────────────

    public function test_index_returns_data_and_meta_envelope(): void
    {
        Complaint::factory()->count(3)->create();

        Sanctum::actingAs(User::factory()->administrator()->create());

        $response = $this->getJson('/api/v1/complaints');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'client_id', 'location_id', 'assigned_employee_id', 'description', 'status', 'created_at', 'updated_at']],
                'meta' => ['total', 'page', 'per_page'],
            ])
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_per_page_is_reflected_in_meta(): void
    {
        Complaint::factory()->count(20)->create();

        Sanctum::actingAs(User::factory()->administrator()->create());

        $this->getJson('/api/v1/complaints?per_page=5')
            ->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 20);
    }

    public function test_store_returns_data_and_translation_key_message(): void
    {
        $location = CustomerLocation::factory()->create();

        Sanctum::actingAs(User::factory()->mitarbeiter()->create());

        $response = $this->postJson('/api/v1/complaints', [
            'location_id' => $location->id,
            'title'       => 'Glasflächen unvollständig',
            'description' => 'Reinigung der Glasflächen im 2. OG unvollständig.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'messages.complaints.created')
            ->assertJsonPath('data.status', ComplaintStatusEnum::Pending->value)
            ->assertJsonStructure(['data' => ['id', 'client_id', 'location_id', 'description', 'status']]);
    }

    // ── client_id vs customer_id ──────────────────────────────────────────

    public function test_client_id_is_written_to_the_customer_id_column(): void
    {
        $customer = Customer::factory()->create();
        $location = CustomerLocation::factory()->create();

        Sanctum::actingAs(User::factory()->mitarbeiter()->create());

        $this->postJson('/api/v1/complaints', [
            'location_id' => $location->id,
            'client_id'   => $customer->id,
            'title'       => 'Test',
            'description' => 'Testbeschreibung',
        ])->assertStatus(201)
          ->assertJsonPath('data.client_id', $customer->id);

        $this->assertDatabaseHas('complaints', [
            'customer_id' => $customer->id,
        ]);
    }

    public function test_client_id_is_readable_through_the_alias(): void
    {
        $customer = Customer::factory()->create();
        $complaint = Complaint::factory()->create(['customer_id' => $customer->id]);

        $this->assertSame($customer->id, $complaint->client_id);
    }

    // ── Validation ────────────────────────────────────────────────────────

    public function test_store_requires_location_title_and_description(): void
    {
        Sanctum::actingAs(User::factory()->mitarbeiter()->create());

        $this->postJson('/api/v1/complaints', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id', 'title', 'description']);
    }

    public function test_store_rejects_unknown_location(): void
    {
        Sanctum::actingAs(User::factory()->mitarbeiter()->create());

        $this->postJson('/api/v1/complaints', [
            'location_id' => 999999,
            'title'       => 'Test',
            'description' => 'Test',
        ])->assertStatus(422)->assertJsonValidationErrors(['location_id']);
    }

    public function test_index_rejects_invalid_status_filter(): void
    {
        Sanctum::actingAs(User::factory()->administrator()->create());

        $this->getJson('/api/v1/complaints?status=nonsense')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/complaints')->assertStatus(401);
    }

    // ── Filtering ─────────────────────────────────────────────────────────

    public function test_can_filter_by_status(): void
    {
        Complaint::factory()->count(2)->pending()->create();
        Complaint::factory()->resolved()->create();

        Sanctum::actingAs(User::factory()->administrator()->create());

        $this->getJson('/api/v1/complaints?status='.ComplaintStatusEnum::Pending->value)
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_can_filter_by_location(): void
    {
        $a = CustomerLocation::factory()->create();
        $b = CustomerLocation::factory()->create();

        Complaint::factory()->create(['location_id' => $a->id]);
        Complaint::factory()->create(['location_id' => $b->id]);

        Sanctum::actingAs(User::factory()->administrator()->create());

        $this->getJson('/api/v1/complaints?location_id='.$a->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.location_id', $a->id);
    }

    public function test_can_filter_by_client_id(): void
    {
        $customer = Customer::factory()->create();
        $other    = Customer::factory()->create();

        Complaint::factory()->create(['customer_id' => $customer->id]);
        Complaint::factory()->create(['customer_id' => $other->id]);

        Sanctum::actingAs(User::factory()->administrator()->create());

        $this->getJson('/api/v1/complaints?client_id='.$customer->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    // ── Access control ────────────────────────────────────────────────────

    public function test_mitarbeiter_only_sees_complaints_assigned_to_them(): void
    {
        $me    = User::factory()->mitarbeiter()->create();
        $other = User::factory()->mitarbeiter()->create();

        Complaint::factory()->assignedTo($me)->create();
        Complaint::factory()->assignedTo($other)->create();
        Complaint::factory()->create(['assigned_employee_id' => null]);

        Sanctum::actingAs($me);

        $response = $this->getJson('/api/v1/complaints')->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($me->id, $response->json('data.0.assigned_employee_id'));
    }

    public function test_vorarbeiter_sees_all_complaints(): void
    {
        Complaint::factory()->count(3)->create();

        Sanctum::actingAs(User::factory()->vorarbeiter()->create());

        $this->getJson('/api/v1/complaints')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_mitarbeiter_cannot_open_a_complaint_assigned_to_someone_else(): void
    {
        $other     = User::factory()->mitarbeiter()->create();
        $complaint = Complaint::factory()->assignedTo($other)->create();

        Sanctum::actingAs(User::factory()->mitarbeiter()->create());

        // 404, not 403 — existence must not be probeable.
        $this->getJson('/api/v1/complaints/'.$complaint->id)->assertStatus(404);
    }

    public function test_mitarbeiter_cannot_change_status(): void
    {
        $complaint = Complaint::factory()->assignedTo(User::factory()->mitarbeiter()->create())->create();

        Sanctum::actingAs(User::factory()->mitarbeiter()->create());

        $this->putJson('/api/v1/complaints/'.$complaint->id.'/status', [
            'status'     => ComplaintStatusEnum::InProgress->value,
            'resolution' => 'Wird erledigt',
        ])->assertStatus(403);
    }

    // ── Status transitions ────────────────────────────────────────────────

    public function test_vorarbeiter_can_advance_status(): void
    {
        $complaint = Complaint::factory()->pending()->create();

        Sanctum::actingAs(User::factory()->vorarbeiter()->create());

        $this->putJson('/api/v1/complaints/'.$complaint->id.'/status', [
            'status' => ComplaintStatusEnum::InProgress->value,
        ])->assertStatus(200)
          ->assertJsonPath('message', 'messages.complaints.status_updated')
          ->assertJsonPath('data.status', ComplaintStatusEnum::InProgress->value);

        $this->assertDatabaseHas('complaints', [
            'id'     => $complaint->id,
            'status' => ComplaintStatusEnum::InProgress->value,
        ]);
    }

    public function test_resolving_stamps_resolver_and_clears_on_reopen(): void
    {
        $admin    = User::factory()->administrator()->create();
        $complaint = Complaint::factory()->pending()->create();

        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/complaints/'.$complaint->id.'/status', [
            'status'     => ComplaintStatusEnum::Resolved->value,
            'resolution' => 'Nachgearbeitet am 01.10.',
        ])->assertStatus(200)
          ->assertJsonPath('data.status', ComplaintStatusEnum::Resolved->value)
          ->assertJsonPath('data.resolution', 'Nachgearbeitet am 01.10.');

        $this->assertNotNull($complaint->fresh()->resolved_at);
        $this->assertSame($admin->id, $complaint->fresh()->resolved_by);

        // Reopening must not leave a stale resolution behind.
        $this->putJson('/api/v1/complaints/'.$complaint->id.'/status', [
            'status' => ComplaintStatusEnum::Pending->value,
        ])->assertStatus(200);

        $reopened = $complaint->fresh();
        $this->assertNull($reopened->resolved_at);
        $this->assertNull($reopened->resolved_by);
        $this->assertNull($reopened->resolution);
    }

    public function test_resolving_without_a_resolution_is_rejected(): void
    {
        $complaint = Complaint::factory()->pending()->create();

        Sanctum::actingAs(User::factory()->administrator()->create());

        $this->putJson('/api/v1/complaints/'.$complaint->id.'/status', [
            'status' => ComplaintStatusEnum::Resolved->value,
        ])->assertStatus(422)
          ->assertJsonPath('message', 'messages.complaints.resolution_required');

        $this->assertSame(
            ComplaintStatusEnum::Pending->value,
            $complaint->fresh()->status->value
        );
    }

    public function test_status_update_rejects_unknown_status(): void
    {
        $complaint = Complaint::factory()->create();

        Sanctum::actingAs(User::factory()->administrator()->create());

        $this->putJson('/api/v1/complaints/'.$complaint->id.'/status', [
            'status' => 'archived',
        ])->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    // ── Side effects: notification + audit ────────────────────────────────

    public function test_creating_a_complaint_notifies_admins_and_writes_audit_log(): void
    {
        $location = CustomerLocation::factory()->create();
        $reporter = User::factory()->mitarbeiter()->create();

        Sanctum::actingAs($reporter);

        $this->postJson('/api/v1/complaints', [
            'location_id' => $location->id,
            'title'       => 'Müll nicht entsorgt',
            'description' => 'Der Müllcontainer war voll.',
        ])->assertStatus(201);

        $this->assertDatabaseHas('admin_notifications', [
            'type'  => AdminNotificationTypeEnum::Alert->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Complaint::class,
            'event'          => AuditEventEnum::Created->value,
            'user_id'        => $reporter->id,
        ]);
    }

    public function test_status_change_is_audited_with_old_and_new_values(): void
    {
        $complaint = Complaint::factory()->pending()->create();
        $admin     = User::factory()->administrator()->create();

        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/complaints/'.$complaint->id.'/status', [
            'status' => ComplaintStatusEnum::InProgress->value,
        ])->assertStatus(200);

        $log = AuditLog::where('auditable_type', Complaint::class)
            ->where('event', AuditEventEnum::Updated->value)
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(ComplaintStatusEnum::Pending->value, $log->old_values['status']);
        $this->assertSame(ComplaintStatusEnum::InProgress->value, $log->new_values['status']);
    }

    public function test_admin_notification_carries_the_complaint_payload(): void
    {
        $location = CustomerLocation::factory()->create(['name' => 'Muster GmbH']);

        Sanctum::actingAs(User::factory()->mitarbeiter()->create());

        $complaintId = $this->postJson('/api/v1/complaints', [
            'location_id' => $location->id,
            'title'       => 'Sanitärbereich',
            'description' => 'Spiegel verschmutzt.',
        ])->json('data.id');

        $notification = AdminNotification::latest('id')->first();

        $this->assertNotNull($notification);
        $this->assertSame($complaintId, $notification->payload['complaint_id']);
        $this->assertStringContainsString('Muster GmbH', (string) $notification->message);
    }
}
