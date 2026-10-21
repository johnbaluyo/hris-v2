@props(['name', 'label' => null, 'type' => 'text', 'options' => [], 'required' => false, 'value' => '', 'help' => null])
@php($id = 'field-'.preg_replace('/[^a-zA-Z0-9_-]/', '-', $name))
<div class="{{ $attributes->get('class', 'col-md-6') }}">
    <label for="{{ $id }}" class="form-label">{{ $label ?? ucwords(str_replace('_', ' ', $name)) }} @if($required)<span aria-hidden="true">*</span>@endif</label>
    @if($type === 'select')
    <select id="{{ $id }}" name="{{ $name }}" class="form-select" @required($required) {{ $attributes->except('class') }}><option value="">Choose…</option>@foreach($options as $key => $text)<option value="{{ $key }}" @selected((string)$value === (string)$key)>{{ $text }}</option>@endforeach</select>
    @elseif($type === 'textarea')
    <textarea id="{{ $id }}" name="{{ $name }}" class="form-control" rows="3" @required($required) {{ $attributes->except('class') }}>{{ $value }}</textarea>
    @else
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}" class="form-control" @required($required) {{ $attributes->except('class') }}>
    @endif
    @if($help)<div class="form-text">{{ $help }}</div>@endif
</div>
