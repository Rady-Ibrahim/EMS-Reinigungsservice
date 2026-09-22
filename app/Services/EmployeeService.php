<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmployeeService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return User::with('employeeProfile')
                   ->whereIn('role', ['vorarbeiter', 'mitarbeiter'])
                   ->orderBy('name')
                   ->paginate($perPage);
    }

    /**
     * Create User + EmployeeProfile in one transaction.
     * Observer handles profile creation automatically — we only need to pass
     * profile-specific fields separately to update after creation.
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name'      => $data['name'],
                'email'     => $data['email'],
                'password'  => Hash::make($data['password']),
                'role'      => $data['role'],
                'locale'    => $data['locale'] ?? 'de',
                'is_active' => true,
            ]);

            // Observer already created the profile — now update with provided fields
            $profileData = array_filter([
                'calendar_color'  => $data['calendar_color'] ?? null,
                'employee_number' => $data['employee_number'] ?? null,
                'phone'           => $data['phone'] ?? null,
                'address'         => $data['address'] ?? null,
                'iban'            => $data['iban'] ?? null,
                'hourly_rate'     => isset($data['hourly_rate']) ? (string) $data['hourly_rate'] : null,
                'contract_type'   => $data['contract_type'] ?? null,
                'joined_at'       => $data['joined_at'] ?? null,
                'notes'           => $data['notes'] ?? null,
            ], fn($v) => $v !== null);

            if (! empty($profileData)) {
                $user->employeeProfile->update($profileData);
            }

            return $user->load('employeeProfile');
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $userFields = array_filter([
                'name'      => $data['name'] ?? null,
                'email'     => $data['email'] ?? null,
                'role'      => $data['role'] ?? null,
                'locale'    => $data['locale'] ?? null,
                'is_active' => $data['is_active'] ?? null,
            ], fn($v) => $v !== null);

            if (! empty($data['password'])) {
                $userFields['password'] = Hash::make($data['password']);
            }

            $user->update($userFields);

            $profileData = array_filter([
                'calendar_color'  => $data['calendar_color'] ?? null,
                'employee_number' => $data['employee_number'] ?? null,
                'phone'           => $data['phone'] ?? null,
                'address'         => $data['address'] ?? null,
                'iban'            => $data['iban'] ?? null,
                'hourly_rate'     => isset($data['hourly_rate']) ? (string) $data['hourly_rate'] : null,
                'contract_type'   => $data['contract_type'] ?? null,
                'joined_at'       => $data['joined_at'] ?? null,
                'notes'           => $data['notes'] ?? null,
            ], fn($v) => $v !== null);

            if (! empty($profileData)) {
                $user->employeeProfile()->updateOrCreate(
                    ['user_id' => $user->id],
                    $profileData
                );
            }

            return $user->fresh('employeeProfile');
        });
    }

    public function delete(User $user): void
    {
        $user->delete(); // soft delete — profile cascades via FK
    }
}
