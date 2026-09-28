@props([
    // Oldest first. Two or more values; with fewer there is no trend to draw.
    'values',
    // What the line shows, for screen readers ("Events per hour today").
    'label',
])

@php
    // A trend line small enough to sit inside a stat card: no axes, no
    // labels. The baseline is zero, so a flat line near the bottom means
    // "almost nothing", not "steady".
    $width = 120;
    $height = 34;
    $pad = 3;
    $count = count($values);
    $max = max(1, max($values ?: [0]));

    $coords = [];
    foreach (array_values($values) as $i => $value) {
        $x = $pad + ($count > 1 ? $i / ($count - 1) : 0) * ($width - 2 * $pad);
        $y = $height - $pad - ($value / $max) * ($height - 2 * $pad);
        $coords[] = [round($x, 1), round($y, 1)];
    }

    $line = 'M' . implode(' L', array_map(fn ($c) => "{$c[0]},{$c[1]}", $coords));
    $area = $line . ' L' . end($coords)[0] . ',' . ($height - $pad) . ' L' . $coords[0][0] . ',' . ($height - $pad) . ' Z';
    $last = end($coords);
@endphp

@if ($count >= 2)
    <svg viewBox="0 0 {{ $width }} {{ $height }}" class="block h-[34px] w-[120px]" role="img" aria-label="{{ $label }}">
        <path d="{{ $area }}" class="fill-brand-tint" />
        <path d="{{ $line }}" class="fill-none stroke-brand" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
        {{-- The newest value, marked so the eye finds "now". --}}
        <circle cx="{{ $last[0] }}" cy="{{ $last[1] }}" r="3" class="fill-brand stroke-surface" stroke-width="1.5" />
    </svg>
@endif
