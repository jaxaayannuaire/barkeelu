@props(['variant' => 'primary', 'href' => null, 'disabled' => false, 'type' => 'button'])
@php
    $variants = [
        'primary' => 'bg-brand-primary text-white hover:bg-brand-primary-hover',
        'secondary' => 'border border-brand-primary bg-white text-brand-primary hover:bg-brand-primary-soft',
        'accent' => 'bg-brand-secondary text-ink hover:bg-brand-secondary-hover',
    ];
    if (! array_key_exists($variant, $variants)) { throw new InvalidArgumentException("Variante de bouton non prise en charge : {$variant}"); }
    $classes = "inline-flex min-h-11 items-center justify-center rounded-[10px] px-4 text-sm font-semibold transition-colors focus:outline-none focus:ring-4 focus:ring-brand-primary-soft {$variants[$variant]}";
@endphp
@if ($href && ! $disabled)
    <a {{ $attributes->class($classes) }} href="{{ $href }}">{{ $slot }}</a>
@else
    <button {{ $attributes->class($classes)->merge(['type' => $type]) }} @disabled($disabled) @if ($disabled) aria-disabled="true" @endif>{{ $slot }}</button>
@endif
