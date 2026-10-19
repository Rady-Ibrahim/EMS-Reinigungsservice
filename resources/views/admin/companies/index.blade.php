@extends('admin.layouts.app')
@section('title', __('company.title'))

@section('content')
<div class="card">
    <div class="card-header">
        <h1 class="card-title">{{ __('company.title') }}</h1>
        <a href="{{ route('admin.companies.create') }}" class="btn btn-primary">
            + {{ __('company.new') }}
        </a>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('company.name') }}</th>
                <th>{{ __('company.contact_person') }}</th>
                <th>{{ __('company.phone') }}</th>
                <th>{{ __('company.employees') }}</th>
                <th>{{ __('company.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($companies as $company)
            <tr>
                <td>{{ $company->id }}</td>
                <td>
                    <a href="{{ route('admin.companies.show', $company) }}">{{ $company->name }}</a>
                </td>
                <td>{{ $company->contact_person ?? '—' }}</td>
                <td>{{ $company->phone ?? '—' }}</td>
                <td>{{ $company->users_count }}</td>
                <td>
                    <div class="actions">
                        <a href="{{ route('admin.companies.edit', $company) }}" class="btn btn-secondary btn-sm">{{ __('company.edit') }}</a>
                        <form action="{{ route('admin.companies.destroy', $company) }}" method="POST"
                              onsubmit="return confirm('{{ __('company.confirm_delete') }}')" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">{{ __('company.delete') }}</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:2rem">{{ __('company.no_companies') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">{{ $companies->links() }}</div>
</div>
@endsection
