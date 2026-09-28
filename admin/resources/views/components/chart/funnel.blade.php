@props([
    // In order: ['label' => string, 'sessions' => int]. Each step's count is
    // already limited to sessions that also did every step before it.
    'steps',
])

@php
    // Bars are scaled to the first step, so each bar's length is the share
    // of visits that got this far.
    $first = max(1, $steps[0]['sessions']);
@endphp

{{-- Plain HTML rather than SVG: horizontal bars with text beside them wrap
     and resize with the card for free. --}}
<ol class="space-y-1 px-5 py-4">
    @foreach ($steps as $i => $step)
        @php
            $previous = $i > 0 ? $steps[$i - 1]['sessions'] : null;
            $share = $step['sessions'] / $first * 100;
            $kept = $previous ? round($step['sessions'] / $previous * 100) : null;
            $lost = $previous !== null ? $previous - $step['sessions'] : 0;
        @endphp

        {{-- What happened between two steps, spelled out: the drop is the
             finding, so it is written, not left for the eye to measure. --}}
        @if ($i > 0)
            <li class="flex items-center gap-2 py-1 ps-3 text-xs text-muted" aria-hidden="true">
                <svg viewBox="0 0 12 12" class="size-3" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 1v10M2.5 7.5 6 11l3.5-3.5"/></svg>
                @if ($previous === 0)
                    no visits reached the step above
                @else
                    {{ $kept }}% continued{{ $lost > 0 ? ' · ' . $lost . ' left here' : '' }}
                @endif
            </li>
        @endif

        <li>
            <div class="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                <span class="font-semibold">{{ $step['label'] }}</span>
                <span class="tabular-nums"><span class="font-bold">{{ number_format($step['sessions']) }}</span>
                    <span class="text-muted">{{ Str::plural('visit', $step['sessions']) }}</span></span>
            </div>
            <div class="h-3 overflow-hidden rounded-full bg-canvas"
                 role="img" aria-label="{{ $step['label'] }}: {{ $step['sessions'] }} of {{ $steps[0]['sessions'] }} visits">
                <div class="h-full rounded-full bg-brand" style="width: {{ round($share, 1) }}%"></div>
            </div>
        </li>
    @endforeach
</ol>
