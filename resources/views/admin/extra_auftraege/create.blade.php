@extends('admin.layouts.app')
@section('title', __('extra_order.new_order_title'))

@section('content')
<h1>{{ __('extra_order.new_order_title') }}</h1>

<div class="card">
    <form action="{{ route('admin.extra-auftraege.store') }}" method="POST" id="orderForm">
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
                <label>{{ __('extra_order.label_internal_cost') }}</label>
                <input type="number" name="internal_cost" step="0.01" min="0" value="{{ old('internal_cost') }}">
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
