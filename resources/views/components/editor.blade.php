@props([
  'name' => 'body',
  'value' => '',
  'label' => null,
  'bare' => false,
  'placeholder' => 'Write something...',
  'paste' => 'plain',
])

<div class="h-editor-wrap">
  @if($label)
    <div class="h-editor-label">{{ $label }}</div>
  @endif
  <div
    class="h-editor {{ $bare ? 'h-editor--bare' : '' }}"
    data-editor
    data-editor-name="{{ $name }}"
    data-placeholder="{{ $placeholder }}"
    data-paste="{{ $paste }}"
    role="textbox"
    aria-multiline="true"
    contenteditable="true"
  >{!! old($name, $value) !!}</div>
</div>
