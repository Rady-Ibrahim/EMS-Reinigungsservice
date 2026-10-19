<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::withCount('users')->orderBy('name')->paginate(15);

        return view('admin.companies.index', compact('companies'));
    }

    public function create()
    {
        return view('admin.companies.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:companies,name'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        Company::create($data);

        return redirect()->route('admin.companies.index')
            ->with('success', __('admin.saved_successfully') ?? 'Gespeichert');
    }

    public function show(Company $company)
    {
        $company->load(['users' => fn ($q) => $q->orderBy('name')]);

        return view('admin.companies.show', compact('company'));
    }

    public function edit(Company $company)
    {
        return view('admin.companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('companies', 'name')->ignore($company->id)],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $company->update($data);

        return redirect()->route('admin.companies.index')
            ->with('success', __('admin.saved_successfully') ?? 'Gespeichert');
    }

    public function destroy(Company $company)
    {
        $company->delete();

        return redirect()->route('admin.companies.index')
            ->with('success', __('admin.deleted_successfully') ?? 'Gelöscht');
    }
}
