@extends('admin.layouts.app')
@section('title', $location->name)

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h1>{{ $location->name }}</h1>
    <div class="actions">
        <a href="{{ route('admin.locations.edit', $location) }}" class="btn btn-secondary">{{ __('customer.edit') }}</a>
        <a href="{{ route('admin.customers.locations.index', $customer) }}" class="btn btn-secondary">{{ __('customer.locations') }}</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;align-items:start">
    <div class="card">
        <div class="card-title" style="margin-bottom:.75rem">{{ __('locations.basics') }}</div>
        <table>
            <tr><td style="color:var(--text-muted);width:150px">{{ __('locations.address') }}</td><td>{{ $location->fullAddress() }}</td></tr>
            <tr><td style="color:var(--text-muted)">{{ __('locations.contact_person') }}</td><td>{{ $location->contact_person ?? '—' }}</td></tr>
            <tr><td style="color:var(--text-muted)">{{ __('locations.contact_phone') }}</td><td>{{ $location->contact_phone ?? '—' }}</td></tr>
            <tr><td style="color:var(--text-muted)">{{ __('locations.working_hours') }}</td><td>{{ $location->working_hours_start ?? '—' }} – {{ $location->working_hours_end ?? '—' }}</td></tr>
            <tr><td style="color:var(--text-muted)">{{ __('locations.status') }}</td><td>
                @if($location->is_active)<span class="badge badge-green">{{ __('locations.active') }}</span>
                @else<span class="badge badge-gray">{{ __('locations.inactive') }}</span>@endif
            </td></tr>
        </table>
        @if($location->access_instructions)
        <div style="margin-top:1rem;padding:.75rem;background:var(--bg-soft);border-radius:8px;font-size:.85rem">
            <strong>{{ __('locations.access_instructions') }}</strong><br>{{ $location->access_instructions }}
        </div>
        @endif
    </div>

    <div class="card" id="documents">
        <div class="card-header">
            <span class="card-title">{{ __('locations.documents') }} ({{ $location->documents->count() }})</span>
        </div>

        <form action="{{ route('admin.locations.documents.store', $location) }}" method="POST" enctype="multipart/form-data" style="margin-bottom:1.25rem;padding:1rem;background:var(--bg-soft);border-radius:10px;border:1px dashed var(--border)">
            @csrf
            <div class="form-group">
                <label>{{ __('locations.document.file') }} (PDF / صور — max 20MB)</label>
                <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.doc,.docx,.xls,.xlsx,.txt,.csv" style="color:var(--text-muted)">
                @error('file')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>{{ __('locations.document.title') }}</label>
                    <input type="text" name="title" value="{{ old('title') }}" placeholder="{{ __('locations.document.untitled') }}">
                </div>
                <div class="form-group">
                    <label>{{ __('locations.visibility') }}</label>
                    <select name="visibility">
                        @foreach(\App\Enums\FileVisibilityEnum::cases() as $v)
                        <option value="{{ $v->value }}" {{ old('visibility') === $v->value ? 'selected' : '' }}>
                            {{ $v->value === 'intern' ? __('locations.visibility_intern') : __('locations.visibility_extern') }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>{{ __('locations.category') }}</label>
                <select name="category">
                    @foreach(\App\Enums\FileCategoryEnum::cases() as $c)
                    <option value="{{ $c->value }}" {{ old('category') === $c->value ? 'selected' : '' }}>{{ $c->label() }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('locations.document.upload') }}</button>
        </form>

        @forelse($location->documents->sortByDesc('created_at') as $doc)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem 0;border-bottom:1px solid var(--border)">
            <div style="min-width:0">
                <div style="font-size:.875rem;font-weight:600">
                    @if(str_contains($doc->mime_type, 'pdf')) 📄 @elseif(str_starts_with($doc->mime_type, 'image/')) 🖼️ @else 📎 @endif
                    {{ $doc->displayTitle() }}
                </div>
                <div style="font-size:.72rem;color:var(--text-faint)">
                    {{ $doc->original_name }} · {{ number_format($doc->size_bytes / 1024, 0) }} KB ·
                    <span class="badge {{ $doc->visibility === 'extern' ? 'badge-blue' : 'badge-gray' }}" style="font-size:.65rem">
                        {{ $doc->visibility === 'extern' ? __('locations.visibility_extern') : __('locations.visibility_intern') }}
                    </span>
                    @if($doc->category && $doc->category !== 'other')
                    <span class="badge badge-yellow" style="font-size:.65rem">{{ $doc->category }}</span>
                    @endif
                </div>
            </div>
            <div class="actions">
                <a href="{{ route('admin.locations.documents.download', [$location, $doc]) }}" class="btn btn-secondary btn-sm">{{ __('locations.download') }}</a>
                <form method="POST" action="{{ route('admin.locations.documents.destroy', [$location, $doc]) }}" onsubmit="return confirm('{{ __('customer.confirm_delete') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">{{ __('customer.delete') }}</button>
                </form>
            </div>
        </div>
        @empty
        <p style="color:var(--text-faint);font-size:.85rem">{{ __('locations.no_documents') }}</p>
        @endforelse
    </div>
</div>
@endsection
