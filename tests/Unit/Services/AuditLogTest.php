<?php

namespace Tests\Unit\Services;

use App\Enums\AuditEventEnum;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_create_event_with_reason_and_actor(): void
    {
        $admin    = User::factory()->administrator()->create();
        $customer = Customer::create([
            'name'           => 'Muster GmbH',
            'contact_person' => 'Max',
            'email'          => 'max@example.com',
            'phone'          => '+4915000',
        ]);

        $logger = new AuditLogger();

        $log = $logger->record(
            $customer,
            AuditEventEnum::Created,
            [],
            ['name' => 'Muster GmbH'],
            'Neukunde angelegt',
            $admin->id
        );

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertEquals($admin->id, $log->user_id);
        $this->assertEquals(Customer::class, $log->auditable_type);
        $this->assertEquals($customer->id, $log->auditable_id);
        $this->assertEquals(AuditEventEnum::Created, $log->event);
        $this->assertEquals('Neukunde angelegt', $log->reason);
        $this->assertEquals(['name' => 'Muster GmbH'], $log->new_values);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Customer::class,
            'auditable_id'   => $customer->id,
            'event'          => AuditEventEnum::Created->value,
        ]);
    }

    public function test_reason_is_required_field_persisted(): void
    {
        $admin = User::factory()->administrator()->create();
        $customer = Customer::create(['name' => 'X GmbH', 'contact_person' => 'A', 'email' => 'a@b.de', 'phone' => '1']);

        (new AuditLogger())->record(
            $customer,
            AuditEventEnum::Reopened,
            ['status' => 'completed'],
            ['status' => 'assigned'],
            'Falsch abgeschlossen',
            $admin->id
        );

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Customer::class,
            'event'          => AuditEventEnum::Reopened->value,
            'reason'         => 'Falsch abgeschlossen',
        ]);
    }
}