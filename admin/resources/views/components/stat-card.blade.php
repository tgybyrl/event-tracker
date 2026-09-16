@props(['label', 'value', 'note' => null, 'pending' => false])

<x-card class="p-5">
    <p class="text-sm font-medium text-muted">{{ $label }}</p>
    <p @class([
        'mt-2 text-[32px] leading-none font-extrabold tracking-tight',
        'text-hairline' => $pending,
    ])>{{ $value }}</p>
    @if ($note)
        <p class="mt-2.5 text-[13px] text-muted">{{ $note }}</p>
    @endif
</x-card>
