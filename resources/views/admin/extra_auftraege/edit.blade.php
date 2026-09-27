@extends('admin.layouts.app')
@section('title', __('extra_order.page_title_edit'))

@section('content')
<h1>{{ __('extra_order.page_title_edit') }}: {{ $extraAuftrag->title }}</h1>

<div class="card">
    <form action="{{ route('admin.extra-auftraege.update', $extraAuftrag) }}" method="POST">
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
                <label>{{ __('extra_order.label_internal_cost') }}</label>
                <input type="number" name="internal_cost" step="0.01" min="0"
                       value="{{ old('internal_cost', $extraAuftrag->internal_cost) }}">
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
@endsection
