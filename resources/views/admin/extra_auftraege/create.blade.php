@extends('admin.layouts.app')
@section('title', __('extra_order.new_order_title'))

@section('content')
<h1>{{ __('extra_order.new_order_title') }}</h1>

<div class="card">
    <form action="{{ route('admin.extra-auftraege.store') }}" method="POST" id="orderForm" enctype="multipart/form-data">
        @csrf

        <h2 style="font-size:1rem;margin-bottom:.75rem;color:#475569">{{ __('extra_order.order_data') }}</h2>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('extra_order.label_title') }}</label>
                <input type="text" name="title" value="{{ old('title') }}" required>
                @error('title')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label>{{ __('extra_order.label_order_type') }}</label>
                <select name="order_type" required>
                    @foreach(\App\Enums\ExtraOrderTypeEnum::cases() as $t)
                    <option value="{{ $t->value }}" {{ old('order_type') === $t->value ? 'selected' : '' }}>
                        {{ $t->label() }}
                    </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>{{ __('extra_order.label_description') }}</label>
            <textarea name="description" rows="3">{{ old('description') }}</textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('extra_order.label_customer') }}</label>
                <select name="customer_id" id="customerSelect" required>
                    <option value="">{{ __('extra_order.select_customer_placeholder') }}</option>
                    @foreach($customers as $c)
                    <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>{{ __('extra_order.label_location') }}</label>
                <select name="location_id" id="locationSelect" required>
                    <option value="">{{ __('extra_order.select_location_placeholder') }}</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('extra_order.label_date') }}</label>
                <input type="date" name="scheduled_date" value="{{ old('scheduled_date') }}" required>
            </div>
            <div class="form-group">
                <label>{{ __('extra_order.label_time') }}</label>
                <input type="time" name="scheduled_time_start" value="{{ old('scheduled_time_start') }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ __('extra_order.label_estimated_hours') }}</label>
                <input type="number" name="estimated_hours" step="0.25" min="0" value="{{ old('estimated_hours') }}">
            </div>
            <div class="form-group">
                <label>{{ __('extra_order.label_travel_time_paid') }}</label>
                <select name="is_travel_time_paid">
                    <option value="0" {{ old('is_travel_time_paid','0') === '0' ? 'selected' : '' }}>{{ __('extra_order.no') }}</option>
                    <option value="1" {{ old('is_travel_time_paid') === '1'  ? 'selected' : '' }}>{{ __('extra_order.yes') }}</option>
                </select>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:1rem 0">
        <h2 style="font-size:1rem;margin-bottom:.75rem;color:#475569">{{ __('extra_order.section_assign_team') }}</h2>
        @error('assignees')<p class="field-error">{{ $message }}</p>@enderror

        <div id="assigneeList">
            <div class="form-row assignee-row" style="margin-bottom:.5rem">
                <select name="assignees[0][user_id]" style="flex:2" required>
                    <option value="">{{ __('extra_order.select_employee_placeholder') }}</option>
                    @foreach($employees as $emp)
                    <option value="{{ $emp->id }}">{{ $emp->name }} ({{ $emp->role->label() }})</option>
                    @endforeach
                </select>
                <select name="assignees[0][role_in_order]" style="flex:1" required>
                    <option value="leader">{{ __('extra_order.role_leader') }}</option>
                    <option value="member">{{ __('extra_order.employee') }}</option>
                </select>
            </div>
        </div>
        <button type="button" onclick="addAssignee()" class="btn btn-secondary btn-sm" style="margin-top:.5rem">{{ __('extra_order.btn_add_employee') }}</button>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:1rem 0">
        <h2 style="font-size:1rem;margin-bottom:.75rem;color:#475569">{{ __('extra_order.section_checklist') }}</h2>
        <div id="checklistContainer">
            <div class="checklist-row" style="display:flex;gap:.5rem;margin-bottom:.5rem">
                <input type="text" name="checklist_template[0][task]" placeholder="{{ __('extra_order.placeholder_task') }}" style="flex:1">
                <button type="button" onclick="this.parentElement.remove()" class="btn btn-danger btn-sm">✕</button>
            </div>
        </div>
        <button type="button" onclick="addChecklist()" class="btn btn-secondary btn-sm">{{ __('extra_order.btn_add_task') }}</button>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:1rem 0">
        <h2 style="font-size:1rem;margin-bottom:.75rem;color:#475569">💰 {{ __('extra_order.section_finance') }} <small style="font-weight:400;font-size:.8rem">({{ __('extra_order.admin_only') }})</small></h2>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('extra_order.label_price') }}</label>
                <input type="number" name="price" step="0.01" min="0" value="{{ old('price') }}">
            </div>
            <div class="form-group">
                <label>{{ __('extra_order.label_price_per_hour') }}</label>
                <input type="number" name="price_per_hour" step="0.01" min="0" value="{{ old('price_per_hour') }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>{{ __('extra_order.label_internal_cost') }}</label>
                <input type="number" name="internal_cost" step="0.01" min="0" value="{{ old('internal_cost') }}">
            </div>
            <div class="form-group">
                <label>{{ __('extra_order.label_profit_margin') }}</label>
                <div style="display:flex;gap:.5rem">
                    <input type="number" name="profit_margin" step="0.01" value="{{ old('profit_margin') }}" style="flex:1">
                    <select name="profit_margin_type" style="width:90px">
                        <option value="eur" {{ old('profit_margin_type', 'eur') === 'eur' ? 'selected' : '' }}>€</option>
                        <option value="pct" {{ old('profit_margin_type') === 'pct' ? 'selected' : '' }}>%</option>
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
            <textarea name="internal_notes" rows="2">{{ old('internal_notes') }}</textarea>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:.5rem">
            <button type="submit" class="btn btn-primary">{{ __('extra_order.btn_create') }}</button>
            <a href="{{ route('admin.extra-auftraege.index') }}" class="btn btn-secondary">{{ __('extra_order.btn_cancel') }}</a>
        </div>
    </form>
</div>

<script>
let assigneeCount = 1;
let checklistCount = 1;
const employees = @json($employees->map(fn($e) => ['id' => $e->id, 'name' => $e->name . ' (' . $e->role->label() . ')']));

document.getElementById('customerSelect').addEventListener('change', function () {
    const customerId = this.value;
    const locationSelect = document.getElementById('locationSelect');
    locationSelect.innerHTML = '<option value="">...</option>';
    if (!customerId) { return; }

    fetch('/admin/api/customers/' + customerId + '/locations', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            locationSelect.innerHTML = '<option value="">{{ __('extra_order.select_location_placeholder') }}</option>';
            data.forEach(loc => {
                locationSelect.innerHTML += '<option value="' + loc.id + '">' + loc.name + ' – ' + (loc.city || '') + '</option>';
            });
        })
        .catch(() => { locationSelect.innerHTML = '<option value="">—</option>'; });
});

function addAssignee() {
    const list = document.getElementById('assigneeList');
    const row = document.createElement('div');
    row.className = 'form-row assignee-row';
    row.style.marginBottom = '.5rem';
    let opts = employees.map(e => `<option value="${e.id}">${e.name}</option>`).join('');
    row.innerHTML = `
        <select name="assignees[${assigneeCount}][user_id]" style="flex:2" required>
            <option value="">{{ __('extra_order.select_employee_placeholder') }}</option>${opts}
        </select>
        <select name="assignees[${assigneeCount}][role_in_order]" style="flex:1">
            <option value="leader">{{ __('extra_order.role_leader') }}</option>
            <option value="member" selected>{{ __('extra_order.employee') }}</option>
        </select>
        <button type="button" onclick="this.parentElement.remove()" class="btn btn-danger btn-sm">✕</button>
    `;
    list.appendChild(row);
    assigneeCount++;
}

function addChecklist() {
    const container = document.getElementById('checklistContainer');
    const row = document.createElement('div');
    row.className = 'checklist-row';
    row.style.cssText = 'display:flex;gap:.5rem;margin-bottom:.5rem';
    row.innerHTML = `
        <input type="text" name="checklist_template[${checklistCount}][task]" placeholder="{{ __('extra_order.placeholder_task') }}" style="flex:1">
        <button type="button" onclick="this.parentElement.remove()" class="btn btn-danger btn-sm">✕</button>
    `;
    container.appendChild(row);
    checklistCount++;
}
</script>
@endsection
