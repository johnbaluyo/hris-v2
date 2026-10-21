@props(['name', 'label' => 'Employee', 'multiple' => false, 'required' => false])
<div class="col-12" data-employee-picker>
    <label for="employee-search-{{ $name }}" class="form-label">Find {{ strtolower($label) }}</label>
    <input id="employee-search-{{ $name }}" class="form-control mb-2" type="search" placeholder="Type a name…" autocomplete="off" data-employee-search>
    <label for="employee-select-{{ $name }}" class="form-label">{{ $label }}</label>
    <select id="employee-select-{{ $name }}" name="{{ $name }}" class="form-select" @if($multiple) multiple size="5" @endif @required($required)></select>
    <div class="d-flex justify-content-between align-items-center mt-2"><span class="small text-body-secondary" data-picker-status role="status"></span><div><button type="button" class="btn btn-sm btn-outline-secondary" data-picker-prev>Previous</button><button type="button" class="btn btn-sm btn-outline-secondary" data-picker-next>Next</button></div></div>
    @if($multiple)<div class="form-text">Selected people remain selected when searching. Use Ctrl / Command for multiple selections.</div>@endif
</div>
