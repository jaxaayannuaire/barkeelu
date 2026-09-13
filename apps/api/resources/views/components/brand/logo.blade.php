@props(['variant' => 'horizontal', 'alt' => 'Barkeelu'])
@php
    $logos = [
        'horizontal' => ['path' => 'images/brand/barkeelu-horizontal.png', 'width' => 512, 'height' => 140],
        'square' => ['path' => 'images/brand/barkeelu-square.png', 'width' => 512, 'height' => 512],
    ];
    if (! array_key_exists($variant, $logos)) { throw new InvalidArgumentException("Variante de logo non prise en charge : {$variant}"); }
    $logo = $logos[$variant];
@endphp
<img {{ $attributes }} src="{{ asset($logo['path']) }}" width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" alt="{{ $alt }}">
