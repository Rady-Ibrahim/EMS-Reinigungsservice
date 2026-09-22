<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Customer\StoreCustomerRequest;
use App\Http\Requests\Admin\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service)
    {
    }

    public function index(): View
    {
        $customers = $this->service->paginate();
        return view('admin.customers.index', compact('customers'));
    }

    public function create(): View
    {
        return view('admin.customers.create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());
        return redirect()->route('admin.customers.index')
                         ->with('success', __('messages.success'));
    }

    public function show(Customer $customer): View
    {
        $customer->load(['locations', 'auditLogs.user']);
        return view('admin.customers.show', compact('customer'));
    }

    public function edit(Customer $customer): View
    {
        return view('admin.customers.edit', compact('customer'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->service->update($customer, $request->validated());
        return redirect()->route('admin.customers.show', $customer)
                         ->with('success', __('messages.success'));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->service->delete($customer);
        return redirect()->route('admin.customers.index')
                         ->with('success', __('messages.customers.deleted'));
    }

    public function toggleStatus(Customer $customer): RedirectResponse
    {
        $this->service->toggleStatus($customer);
        return back()->with('success', __('messages.customers.status_updated'));
    }
}
