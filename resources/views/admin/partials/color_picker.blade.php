@php
    $pickerId = $pickerId ?? 'cp_' . \Illuminate\Support\Str::random(6);
    $fieldName = $name ?? 'calendar_color';
    $value = old($fieldName, $value ?? '#D4AF37');
@endphp
<div style="display:flex;gap:.5rem;align-items:center">
    <input type="color" id="{{ $pickerId }}" value="{{ $value }}"
           style="width:52px;height:42px;padding:2px;border-radius:9px;cursor:pointer;background:var(--bg-soft);border:1px solid var(--border)"
           oninput="document.getElementById('{{ $pickerId }}_text').value = this.value.toUpperCase()">
    <input type="text" id="{{ $pickerId }}_text" name="{{ $fieldName }}" value="{{ $value }}"
           pattern="^#[0-9A-Fa-f]{6}$"
           style="flex:1"
           oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)) document.getElementById('{{ $pickerId }}').value = this.value">
</div>
