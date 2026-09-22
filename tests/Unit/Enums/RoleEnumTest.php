<?php

namespace Tests\Unit\Enums;

use App\Enums\RoleEnum;
use Tests\TestCase;

class RoleEnumTest extends TestCase
{
    public function test_administrator_is_admin_role(): void
    {
        $this->assertTrue(RoleEnum::Administrator->isAdminRole());
        $this->assertFalse(RoleEnum::Administrator->isApiRole());
    }

    public function test_vorarbeiter_is_api_role(): void
    {
        $this->assertTrue(RoleEnum::Vorarbeiter->isApiRole());
        $this->assertFalse(RoleEnum::Vorarbeiter->isAdminRole());
    }

    public function test_mitarbeiter_is_api_role(): void
    {
        $this->assertTrue(RoleEnum::Mitarbeiter->isApiRole());
        $this->assertFalse(RoleEnum::Mitarbeiter->isAdminRole());
    }

    public function test_values_returns_all_role_strings(): void
    {
        $values = RoleEnum::values();
        $this->assertContains('administrator', $values);
        $this->assertContains('vorarbeiter', $values);
        $this->assertContains('mitarbeiter', $values);
        $this->assertCount(3, $values);
    }

    public function test_labels_are_correct(): void
    {
        $this->assertSame('Administrator', RoleEnum::Administrator->label());
        $this->assertSame('Vorarbeiter',   RoleEnum::Vorarbeiter->label());
        $this->assertSame('Mitarbeiter',   RoleEnum::Mitarbeiter->label());
    }

    public function test_enum_can_be_created_from_string(): void
    {
        $this->assertSame(RoleEnum::Administrator, RoleEnum::from('administrator'));
        $this->assertSame(RoleEnum::Vorarbeiter,   RoleEnum::from('vorarbeiter'));
        $this->assertSame(RoleEnum::Mitarbeiter,   RoleEnum::from('mitarbeiter'));
    }
}
