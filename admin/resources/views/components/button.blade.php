@props(['variant' => 'secondary', 'href' => null])

@php
    $variants = [
        'primary' => 'bg-brand text-white hover:bg-brand-ink',
        'secondary' => 'border border-hairline bg-surface text-ink hover:bg-canvas',
    ];

    $classes = 'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold whitespace-nowrap transition-colors '
        . ($variants[$variant] ?? $variants['secondary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->class($classes)->merge(['type' => 'button']) }}>{{ $slot }}</button>
@endif
