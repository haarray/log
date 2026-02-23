@props([
  'name',
  'label' => null,
  'options' => [],
  'value' => null,
  'placeholder' => 'Select one',
  'multiple' => false,
  'search' => true,
  'id' => null,
])

@php
  $fieldId = $id ?? $name;
  $selectedValues = collect((array) old($name, $value))->map(fn ($item) => (string) $item)->all();
@endphp

<div class="h-form-group">
  @if($label)
    <label class="h-label" for="{{ $fieldId }}">{{ $label }}</label>
  @endif

  <select
    id="{{ $fieldId }}"
    name="{{ $multiple ? $name.'[]' : $name }}"
    class="h-select h-select2"
    data-h-select
    data-h-search="{{ $search ? 'true' : 'false' }}"
    data-placeholder="{{ $placeholder }}"
    @if($multiple) multiple @endif
  >
    @unless($multiple)
      <option value="">{{ $placeholder }}</option>
    @endunless

    @foreach($options as $optionValue => $optionLabel)
      @php $optionValue = (string) $optionValue; @endphp
      <option value="{{ $optionValue }}" @selected(in_array($optionValue, $selectedValues, true))>{{ $optionLabel }}</option>
    @endforeach
  </select>
</div>
