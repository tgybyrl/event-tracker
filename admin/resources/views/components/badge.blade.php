@props(['tone' => 'slate'])

@php
    // Colour carries meaning here, so the tone is looked up from the value
    // rather than passed in by hand at every call site.
    $tones = [
        'blue' => 'bg-tag-blue text-tag-blue-ink',
        'green' => 'bg-tag-green text-tag-green-ink',
        'amber' => 'bg-tag-amber text-tag-amber-ink',
        'violet' => 'bg-tag-violet text-tag-violet-ink',
        'rose' => 'bg-tag-rose text-tag-rose-ink',
        'slate' => 'bg-tag-slate text-tag-slate-ink',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold whitespace-nowrap',
    $tones[$tone] ?? $tones['slate'],
]) }}>{{ $slot }}</span>
