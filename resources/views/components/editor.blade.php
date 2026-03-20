@props([
  'name' => 'body',
  'value' => '',
  'label' => null,
  'bare' => false,
  'placeholder' => 'Write something...',
  'paste' => 'plain',
])

@php
  $initialValue = old($name, $value);
@endphp

<div class="h-editor-wrap">
  @if($label)
    <div class="h-editor-label">{{ $label }}</div>
  @endif
  <textarea name="{{ $name }}" data-editor-hidden="{{ $name }}" hidden>{!! $initialValue !!}</textarea>
  <div
    class="h-editor {{ $bare ? 'h-editor--bare' : '' }}"
    data-editor
    data-editor-name="{{ $name }}"
    data-placeholder="{{ $placeholder }}"
    data-paste="{{ $paste }}"
    role="textbox"
    aria-multiline="true"
    contenteditable="true"
  >{!! $initialValue !!}</div>
  <noscript>
    <textarea name="{{ $name }}" class="form-control" rows="8" placeholder="{{ $placeholder }}">{{ $initialValue }}</textarea>
  </noscript>
</div>
