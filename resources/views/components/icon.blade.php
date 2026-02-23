@props([
  'name',
  'class' => 'h-icon',
  'w' => 16,
  'h' => 16,
  'label' => null,
])

<svg
  class="{{ $class }}"
  width="{{ $w }}"
  height="{{ $h }}"
  viewBox="0 0 24 24"
  @if($label)
    role="img"
    aria-label="{{ $label }}"
  @else
    aria-hidden="true"
  @endif
>
  <use href="{{ asset('/icons/icons.svg') }}#{{ $name }}" xlink:href="{{ asset('/icons/icons.svg') }}#{{ $name }}"></use>
</svg>
