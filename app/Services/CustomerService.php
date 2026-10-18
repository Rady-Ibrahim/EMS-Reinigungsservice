<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerLocation;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Customer::withCount('locations')
                       ->orderBy('name')
                       ->paginate($perPage);
    }

    public function create(array $data): Customer
    {
        $locations = $data['locations'] ?? [];
        unset($data['locations']);

        $customer = Customer::create($data);
        $this->syncLocations($customer, $locations);

        return $customer;
    }

    public function update(Customer $customer, array $data): Customer
    {
        $locations = $data['locations'] ?? null;
        unset($data['locations']);

        $customer->update($data);

        if ($locations !== null) {
            $this->syncLocations($customer, $locations);
        }

        return $customer->fresh();
    }

    /**
     * Create/update the customer's locations from the inline form payload.
     * Existing locations not present in the payload are left untouched.
     */
    private function syncLocations(Customer $customer, array $locations): void
    {
        foreach ($locations as $row) {
            $id = $row['id'] ?? null;
            unset($row['id']);

            if ($id) {
                $location = $customer->locations()->find($id);
                if ($location) {
                    $location->update($row);
                    continue;
                }
            }

            $customer->locations()->create($row);
        }
    }

    public function delete(Customer $customer): void
    {
        $customer->delete(); // soft delete
    }

    public function restore(int $id): Customer
    {
        $customer = Customer::withTrashed()->findOrFail($id);
        $customer->restore();
        return $customer;
    }

    public function toggleStatus(Customer $customer): Customer
    {
        $customer->update([
            'status' => $customer->isActive()
                ? \App\Enums\CustomerStatusEnum::Inactive
                : \App\Enums\CustomerStatusEnum::Active,
        ]);
        return $customer->fresh();
    }
}
