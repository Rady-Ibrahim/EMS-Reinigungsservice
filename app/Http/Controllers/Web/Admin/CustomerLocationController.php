<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Location\StoreLocationRequest;
use App\Http\Requests\Admin\Location\UpdateLocationRequest;
use App\Models\Customer;
use App\Models\CustomerLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerLocationController extends Controller
{
    public function index(Customer $customer): View
    {
        $locations = $customer->locations()->withTrashed()->paginate(15);
        return view('admin.locations.index', compact('customer', 'locations'));
    }

    public function create(Customer $customer): View
    {
        return view('admin.locations.create', compact('customer'));
    }

    public function store(StoreLocationRequest $request, Customer $customer): RedirectResponse
    {
        $data = array_merge($request->validated(), ['customer_id' => $customer->id]);
        $customer->locations()->create($data);

        return redirect()->route('admin.customers.locations.index', $customer)
                         ->with('success', __('messages.success'));
    }

    public function show(Customer $customer, CustomerLocation $location): View
    {
        $location->load(['files.uploader', 'auditLogs.user']);
        return view('admin.locations.show', compact('customer', 'location'));
    }

    public function edit(Customer $customer, CustomerLocation $location): View
    {
        return view('admin.locations.edit', compact('customer', 'location'));
    }

    public function update(UpdateLocationRequest $request, Customer $customer, CustomerLocation $location): RedirectResponse
    {
        $location->update($request->validated());
        return redirect()->route('admin.customers.locations.show', [$customer, $location])
                         ->with('success', __('messages.success'));
    }

    public function destroy(Customer $customer, CustomerLocation $location): RedirectResponse
    {
        $location->delete();
        return redirect()->route('admin.customers.locations.index', $customer)
                         ->with('success', __('messages.locations.deleted'));
    }
}
