<?php

namespace App\Observers;

use App\Enums\RoleEnum;
use App\Models\EmployeeProfile;
use App\Models\User;

class UserObserver
{
    /**
     * Auto-create an EmployeeProfile when a Vorarbeiter or Mitarbeiter is created.
     * Generates a unique calendar color from a predefined palette to avoid clashes.
     */
    public function created(User $user): void
    {
        if (! $user->role->isApiRole()) {
            return;
        }

        EmployeeProfile::create([
            'user_id'        => $user->id,
            'calendar_color' => $this->generateUniqueColor(),
        ]);
    }

    /**
     * When a user's role changes TO an API role, ensure a profile exists.
     * When changed AWAY from an API role, do nothing (preserve historical data).
     */
    public function updated(User $user): void
    {
        if (! $user->wasChanged('role')) {
            return;
        }

        if ($user->role->isApiRole() && ! $user->employeeProfile()->exists()) {
            EmployeeProfile::create([
                'user_id'        => $user->id,
                'calendar_color' => $this->generateUniqueColor(),
            ]);
        }
    }

    /**
     * Pick a color from the palette that is not yet used by another employee.
     * Falls back to a random hex if all palette colors are taken.
     */
    private function generateUniqueColor(): string
    {
        $palette = [
            '#3b82f6', '#10b981', '#f59e0b', '#ef4444',
            '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16',
            '#f97316', '#6366f1', '#14b8a6', '#e11d48',
            '#7c3aed', '#0284c7', '#16a34a', '#b45309',
        ];

        $usedColors = EmployeeProfile::pluck('calendar_color')->toArray();

        foreach ($palette as $color) {
            if (! in_array($color, $usedColors)) {
                return $color;
            }
        }

        // All palette colors used — generate a random one
        return sprintf('#%06x', mt_rand(0, 0xFFFFFF));
    }
}
