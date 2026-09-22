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
        return Customer::create($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        return $customer->fresh();
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
