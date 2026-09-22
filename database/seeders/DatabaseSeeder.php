<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ── Admin account ────────────────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'admin@ems-service.de'],
            [
                'name'              => 'EMS Administrator',
                'password'          => Hash::make('Admin@2024!'),
                'role'              => RoleEnum::Administrator,
                'locale'            => 'de',
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );

        // ── Sample Vorarbeiter ───────────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'vorarbeiter@ems-service.de'],
            [
                'name'              => 'Klaus Müller',
                'password'          => Hash::make('Worker@2024!'),
                'role'              => RoleEnum::Vorarbeiter,
                'locale'            => 'de',
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );

        // ── Sample Mitarbeiter ───────────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'mitarbeiter@ems-service.de'],
            [
                'name'              => 'Ahmed Hassan',
                'password'          => Hash::make('Worker@2024!'),
                'role'              => RoleEnum::Mitarbeiter,
                'locale'            => 'ar',
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✓ Default users seeded successfully.');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                [RoleEnum::Administrator->label(), 'admin@ems-service.de',       'Admin@2024!'],
                [RoleEnum::Vorarbeiter->label(),   'vorarbeiter@ems-service.de', 'Worker@2024!'],
                [RoleEnum::Mitarbeiter->label(),   'mitarbeiter@ems-service.de', 'Worker@2024!'],
            ]
        );
    }
}
