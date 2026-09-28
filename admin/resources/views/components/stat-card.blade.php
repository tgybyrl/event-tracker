@props(['label', 'value', 'note' => null, 'pending' => false])

<x-card class="p-5">
    <p class="text-sm font-medium text-muted">{{ $label }}</p>
    {{-- The optional chart slot (a sparkline) sits beside the number, so the
         card says both "how many" and "which way it is going". --}}
    <div class="mt-2 flex items-end justify-between gap-3">
        <p @class([
            'text-[32px] leading-none font-extrabold tracking-tight',
            'text-hairline' => $pending,
        ])>{{ $value }}</p>
        @isset($chart)
            <div class="shrink-0">{{ $chart }}</div>
        @endisset
    </div>
    @if ($note)
        <p class="mt-2.5 text-[13px] text-muted">{{ $note }}</p>
    @endif
</x-card>
