@extends('admin.layouts.app')
@section('title', __('extra_order.page_title_edit'))

@section('content')
<h1>{{ __('extra_order.page_title_edit') }}: {{ $extraAuftrag->title }}</h1>

<div class="card">
    <form action="{{ route('admin.extra-auftraege.update', $extraAuftrag) }}" method="POST" enctype="multipart/form-data">
        @csrf @method('PUT')

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('extra_order.label_title') }}</label>
                <input type="text" name="title" value="{{ old('title', $extraAuftrag->title) }}" required>
            </div>
            <div class="form-group">
                <label>{{ __('extra_order.label_order_type') }}</label>
                <select name="order_type" required>
                    @foreach(\App\Enums\ExtraOrderTypeEnum::cases() as $t)
                    <option value="{{ $t->value }}" {{ old('order_type', $extraAuftrag->order_type->value) === $t->value ? 'selected' : '' }}>
                        {{ $t->label() }}
                    </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>{{ __('extra_order.label_description') }}</label>
            <textarea name="description" rows="3">{{ old('description', $extraAuftrag->description) }}</textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('extra_order.label_date') }}</label>
                <input type="date" name="scheduled_date" value="{{ old('scheduled_date', $extraAuftrag->scheduled_date->toDateString()) }}" required>
            </div>
            <div class="form-group">
                <label>{{ __('extra_order.label_time') }}</label>
                <input type="time" name="scheduled_time_start"
                       value="{{ old('scheduled_time_start', $extraAuftrag->scheduled_time_start ? substr($extraAuftrag->scheduled_time_start,0,5) : '') }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('extra_order.label_estimated_hours') }}</label>
                <input type="number" name="estimated_hours" step="0.25" min="0"
                       value="{{ old('estimated_hours', $extraAuftrag->estimated_hours) }}">
            </div>
            <div class="form-group">
                <label>{{ __('extra_order.label_travel_time_paid') }}</label>
                <select name="is_travel_time_paid">
                    <option value="0" {{ !old('is_travel_time_paid', $extraAuftrag->is_travel_time_paid) ? 'selected' : '' }}>{{ __('extra_order.no') }}</option>
                    <option value="1" {{ old('is_travel_time_paid',  $extraAuftrag->is_travel_time_paid) ? 'selected' : '' }}>{{ __('extra_order.yes') }}</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('extra_order.label_price') }}</label>
                <input type="number" name="price" step="0.01" min="0"
                       value="{{ old('price', $extraAuftrag->price) }}">
            </div>
            <div class="form-group">
                <label>{{ __('extra_order.label_price_per_hour') }}</label>
                <input type="number" name="price_per_hour" step="0.01" min="0"
                       value="{{ old('price_per_hour', $extraAuftrag->price_per_hour) }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('extra_order.label_internal_cost') }}</label>
                <input type="number" name="internal_cost" step="0.01" min="0"
                       value="{{ old('internal_cost', $extraAuftrag->internal_cost) }}">
            </div>
            <div class="form-group">
                <label>{{ __('extra_order.label_profit_margin') }}</label>
                <div style="display:flex;gap:.5rem">
                    <input type="number" name="profit_margin" step="0.01" value="{{ old('profit_margin', $extraAuftrag->profit_margin) }}" style="flex:1">
                    <select name="profit_margin_type" style="width:100px">
                        <option value="eur" {{ old('profit_margin_type', $extraAuftrag->profit_margin_type) === 'eur' ? 'selected' : '' }}>€</option>
                        <option value="pct" {{ old('profit_margin_type', $extraAuftrag->profit_margin_type) === 'pct' ? 'selected' : '' }}>%</option>
                    </select>
                </div>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:1rem 0">
        <h2 style="font-size:1rem;margin-bottom:.5rem;color:#475569">🔒 {{ __('extra_order.section_documents_intern') }}</h2>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('extra_order.document_label') }}</label>
                <input type="file" name="intern_document" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.doc,.docx,.xls,.xlsx,.txt,.csv" style="color:var(--text-muted)">
                <small style="color:var(--text-faint)">{{ __('extra_order.document_hint') }}</small>
                @error('intern_document')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label>{{ __('extra_order.document_title') }}</label>
                <input type="text" name="intern_document_title" value="{{ old('intern_document_title') }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('extra_order.document_category') }}</label>
                <select name="intern_document_category">
                    @foreach(\App\Enums\FileCategoryEnum::cases() as $cat)
                    <option value="{{ $cat->value }}" {{ old('intern_document_category', 'other') === $cat->value ? 'selected' : '' }}>{{ $cat->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>{{ __('extra_order.document_notes') }}</label>
                <textarea name="intern_document_notes" rows="2">{{ old('intern_document_notes') }}</textarea>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:1rem 0">
        <h2 style="font-size:1rem;margin-bottom:.5rem;color:#475569">👥 {{ __('extra_order.section_documents_extern') }}</h2>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('extra_order.document_label') }}</label>
                <input type="file" name="extern_document" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.doc,.docx,.xls,.xlsx,.txt,.csv" style="color:var(--text-muted)">
                <small style="color:var(--text-faint)">{{ __('extra_order.document_hint') }}</small>
                @error('extern_document')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label>{{ __('extra_order.document_title') }}</label>
                <input type="text" name="extern_document_title" value="{{ old('extern_document_title') }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('extra_order.document_category') }}</label>
                <select name="extern_document_category">
                    @foreach(\App\Enums\FileCategoryEnum::cases() as $cat)
                    <option value="{{ $cat->value }}" {{ old('extern_document_category', 'contract') === $cat->value ? 'selected' : '' }}>{{ $cat->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>{{ __('extra_order.document_notes') }}</label>
                <textarea name="extern_document_notes" rows="2">{{ old('extern_document_notes') }}</textarea>
            </div>
        </div>

        <div class="form-group">
            <label>{{ __('extra_order.label_internal_notes') }}</label>
            <textarea name="internal_notes" rows="2">{{ old('internal_notes', $extraAuftrag->internal_notes) }}</textarea>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:.5rem">
            <button type="submit" class="btn btn-primary">{{ __('extra_order.btn_save') }}</button>
            <a href="{{ route('admin.extra-auftraege.show', $extraAuftrag) }}" class="btn btn-secondary">{{ __('extra_order.btn_cancel') }}</a>
        </div>
    </form>
</div>

{{-- Existing documents (own forms — no nested forms) --}}
<div class="card" style="margin-top:1rem">
    <div class="card-title" style="margin-bottom:.75rem">{{ __('extra_order.existing_documents') }}</div>
    @forelse($extraAuftrag->documents as $doc)
    <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid #f1f5f9">
        <div style="font-size:.875rem">
            <a href="{{ route('admin.extra-auftraege.documents.download', [$extraAuftrag, $doc]) }}">{{ $doc->displayTitle() }}</a>
            <small style="color:#94a3b8;display:block">
                {{ $doc->visibility->label() }} · {{ $doc->category->label() }}
                @if($doc->notes) · {{ $doc->notes }} @endif
            </small>
        </div>
        <form action="{{ route('admin.extra-auftraege.documents.destroy', [$extraAuftrag, $doc]) }}" method="POST" onsubmit="return confirm('{{ __('extra_order.confirm_cancel') }}')">
            @csrf @method('DELETE')
            <button class="btn btn-danger btn-sm">{{ __('fix_object.delete') }}</button>
        </form>
    </div>
    @empty
    <p style="color:#94a3b8;font-size:.85rem">{{ __('extra_order.no_documents') }}</p>
    @endforelse
</div>
@endsection
