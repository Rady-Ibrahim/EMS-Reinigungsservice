@extends('admin.layouts.app')
@section('title', __('fix_object.edit_title'))

@section('content')
<h1>{{ __('fix_object.edit_heading') }} {{ $fixObject->title }}</h1>

<div class="card">
    <form action="{{ route('admin.fix-objects.update', $fixObject) }}" method="POST" enctype="multipart/form-data">
        @csrf @method('PUT')

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('fix_object.customer_label') }}</label>
                <select name="customer_id" id="customerSelect">
                    <option value="">{{ __('fix_object.customer_placeholder') }}</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ old('customer_id', $fixObject->customer_id) == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>{{ __('fix_object.location_label') }}</label>
                <select name="location_id" id="locationSelect">
                    <option value="">{{ __('fix_object.location_placeholder') }}</option>
                    @if($fixObject->location)
                        <option value="{{ $fixObject->location->id }}" selected>{{ $fixObject->location->name }} – {{ $fixObject->location->city }}</option>
                    @endif
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('company.company') }}</label>
                <select name="company_id" id="companySelect">
                    <option value="">{{ __('company.none') }}</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" {{ (string) old('company_id', $fixObject->company_id) === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                    @endforeach
                </select>
                @error('company_id')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('fix_object.title_label') }}</label>
                <input type="text" name="title" value="{{ old('title', $fixObject->title) }}" required>
            </div>
            <div class="form-group">
                <label>{{ __('fix_object.calendar_color') }}</label>
                @include('admin.partials.color_picker', ['name' => 'calendar_color', 'value' => old('calendar_color', $fixObject->calendar_color)])
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('fix_object.frequency_label') }}</label>
                <select name="frequency" required>
                    @foreach(\App\Enums\FixFrequencyEnum::cases() as $freq)
                    <option value="{{ $freq->value }}" {{ old('frequency', $fixObject->frequency->value) === $freq->value ? 'selected' : '' }}>
                        {{ $freq->label() }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>{{ __('fix_object.contract_hours_short_label') }}</label>
                <input type="number" name="contract_hours" step="0.25" min="0.25" max="24"
                       value="{{ old('contract_hours', $fixObject->contract_hours) }}" required>
            </div>
        </div>

        <div class="form-group">
            <label>{{ __('fix_object.weekdays') }}</label>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.25rem">
                @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)
                <label style="display:flex;align-items:center;gap:.3rem;font-weight:normal;cursor:pointer">
                    <input type="checkbox" name="frequency_days[]" value="{{ $day }}"
                           {{ in_array($day, old('frequency_days', $fixObject->frequency_days ?? [])) ? 'checked' : '' }}>
                    {{ $day }}
                </label>
                @endforeach
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('fix_object.time_start') }}</label>
                <input type="time" name="time_start" value="{{ old('time_start', $fixObject->time_start ? substr($fixObject->time_start, 0, 5) : '') }}">
            </div>
            <div class="form-group">
                <label>{{ __('fix_object.time_end') }}</label>
                <input type="time" name="time_end" value="{{ old('time_end', $fixObject->time_end ? substr($fixObject->time_end, 0, 5) : '') }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('fix_object.valid_from') }}</label>
                <input type="date" name="valid_from" value="{{ old('valid_from', $fixObject->valid_from->toDateString()) }}" required>
            </div>
            <div class="form-group">
                <label>{{ __('fix_object.valid_until') }}</label>
                <input type="date" name="valid_until" value="{{ old('valid_until', $fixObject->valid_until?->toDateString()) }}">
            </div>
        </div>

        <div class="form-group">
            <label>{{ __('fix_object.status') }}</label>
            <select name="is_active">
                <option value="1" {{ old('is_active', $fixObject->is_active) ? 'selected' : '' }}>{{ __('fix_object.active') }}</option>
                <option value="0" {{ !old('is_active', $fixObject->is_active) ? 'selected' : '' }}>{{ __('fix_object.inactive') }}</option>
            </select>
        </div>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:1rem 0">
        <h2 style="font-size:1rem;margin-bottom:.75rem;color:#475569">{{ __('fix_object.financial_data') }}</h2>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('fix_object.price_per_month') }}</label>
                <input type="number" name="price_per_month" step="0.01" min="0"
                       value="{{ old('price_per_month', $fixObject->price_per_month) }}">
            </div>
            <div class="form-group">
                <label>{{ __('fix_object.price_per_hour') }}</label>
                <input type="number" name="price_per_hour" step="0.01" min="0"
                       value="{{ old('price_per_hour', $fixObject->price_per_hour) }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('fix_object.internal_cost') }}</label>
                <input type="number" name="internal_cost" step="0.01" min="0"
                       value="{{ old('internal_cost', $fixObject->internal_cost) }}">
            </div>
            <div class="form-group">
                <label>{{ __('fix_object.profit_margin') }}</label>
                <input type="number" name="profit_margin" step="0.01"
                       value="{{ old('profit_margin', $fixObject->profit_margin) }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('company.payout') }}</label>
                <input type="number" name="company_payout" step="0.01" min="0"
                       value="{{ old('company_payout', $fixObject->company_payout) }}">
                <small style="color:var(--text-faint)">{{ __('company.payout_hint') }}</small>
            </div>
        </div>
        <div class="form-group">
            <label>{{ __('fix_object.internal_notes') }}</label>
            <textarea name="internal_notes" rows="3">{{ old('internal_notes', $fixObject->internal_notes) }}</textarea>
        </div>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:1rem 0">
        <h2 style="font-size:1rem;margin-bottom:.5rem;color:#475569">📎 {{ __('fix_object.documents') }}</h2>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('fix_object.document_label') }}</label>
                <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.doc,.docx,.xls,.xlsx,.txt,.csv" style="color:var(--text-muted)">
                <small style="color:var(--text-faint)">{{ __('fix_object.document_hint') }}</small>
                @error('document')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label>{{ __('fix_object.document_title') }}</label>
                <input type="text" name="document_title" value="{{ old('document_title') }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('fix_object.document_visibility') }}</label>
                <select name="document_visibility">
                    @foreach(\App\Enums\FileVisibilityEnum::cases() as $vis)
                    <option value="{{ $vis->value }}" {{ old('document_visibility', 'extern') === $vis->value ? 'selected' : '' }}>{{ $vis->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>{{ __('fix_object.document_category') }}</label>
                <select name="document_category">
                    @foreach(\App\Enums\FileCategoryEnum::cases() as $cat)
                    <option value="{{ $cat->value }}" {{ old('document_category', 'contract') === $cat->value ? 'selected' : '' }}>{{ $cat->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>{{ __('fix_object.document_notes') }}</label>
            <textarea name="document_notes" rows="2">{{ old('document_notes') }}</textarea>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:.5rem">
            <button type="submit" class="btn btn-primary">{{ __('fix_object.save') }}</button>
            <a href="{{ route('admin.fix-objects.show', $fixObject) }}" class="btn btn-secondary">{{ __('fix_object.cancel') }}</a>
        </div>
    </form>
</div>

{{-- Existing documents (own forms — no nested forms) --}}
<div class="card" style="margin-top:1rem">
    <div class="card-title" style="margin-bottom:.75rem">{{ __('fix_object.existing_documents') }}</div>
    @forelse($fixObject->documents as $doc)
    <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid #f1f5f9">
        <div style="font-size:.875rem">
            <a href="{{ route('admin.fix-objects.documents.download', [$fixObject, $doc]) }}">{{ $doc->displayTitle() }}</a>
            <small style="color:#94a3b8;display:block">
                {{ $doc->visibility->label() }} · {{ $doc->category->label() }}
                @if($doc->notes) · {{ $doc->notes }} @endif
            </small>
        </div>
        <form action="{{ route('admin.fix-objects.documents.destroy', [$fixObject, $doc]) }}" method="POST" onsubmit="return confirm('{{ __('fix_object.confirm_delete') }}')">
            @csrf @method('DELETE')
            <button class="btn btn-danger btn-sm">{{ __('fix_object.delete') }}</button>
        </form>
    </div>
    @empty
    <p style="color:#94a3b8;font-size:.85rem">{{ __('fix_object.no_documents') }}</p>
    @endforelse
</div>

<script>
document.getElementById('customerSelect').addEventListener('change', function() {
    const customerId = this.value;
    const locationSelect = document.getElementById('locationSelect');
    locationSelect.innerHTML = '<option value="">' + @json(__('fix_object.loading')) + '</option>';
    if (!customerId) { return; }

    fetch(`/admin/api/customers/${customerId}/locations`, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            locationSelect.innerHTML = '<option value="">' + @json(__('fix_object.location_placeholder')) + '</option>';
            data.forEach(loc => {
                locationSelect.innerHTML += `<option value="${loc.id}">${loc.name} – ${loc.city || ''}</option>`;
            });
        })
        .catch(() => { locationSelect.innerHTML = '<option value="">—</option>'; });
});
</script>
@endsection