@props([
    // Each point: ['label' => ?string, 'value' => int, 'tip' => string, 'partial' => bool].
    // label is null for points whose x label is skipped to avoid crowding.
    'points',
    // Read out by screen readers, and the caption of the hidden data table.
    'summary',
])

@php
    // A column chart drawn as one SVG. All geometry is computed here, in the
    // SVG's own coordinate space (1000 wide); the browser scales it to fit.
    // 1000 is close to the card's real width on a laptop, so the text lands
    // near its intended size instead of being blown up or shrunk.
    $width = 1000;
    $height = 280;
    $left = 44;    // room for the y-axis numbers
    $right = 8;
    $top = 14;
    $bottom = 30;  // room for the x-axis labels
    $plotW = $width - $left - $right;
    $plotH = $height - $top - $bottom;
    $base = $top + $plotH;

    // The axis tops out at a round number above the tallest column, so the
    // gridlines read 0 / 20 / 40 rather than 0 / 17 / 34.
    $max = max(1, max(array_column($points, 'value')));
    $roughStep = $max / 3;
    $magnitude = 10 ** floor(log10($roughStep));
    $fraction = $roughStep / $magnitude;
    $step = ($fraction <= 1 ? 1 : ($fraction <= 2 ? 2 : ($fraction <= 5 ? 5 : 10))) * $magnitude;
    $step = max(1, (int) $step);
    $axisTop = (int) (ceil($max / $step) * $step);
    $ticks = range(0, $axisTop, $step);

    $count = count($points);
    $slot = $plotW / $count;
    $barW = min(34, $slot * 0.62);
    $y = fn ($value) => $base - ($value / $axisTop) * $plotH;

    // A column with a rounded top and a square foot, standing on the axis.
    $column = function ($x, $value) use ($barW, $base, $y) {
        $top = $y($value);
        $r = min(4, $base - $top, $barW / 2);
        $right = $x + $barW;
        return "M{$x},{$base} V" . ($top + $r) . " Q{$x},{$top} " . ($x + $r) . ",{$top}"
            . " H" . ($right - $r) . " Q{$right},{$top} {$right}," . ($top + $r) . " V{$base} Z";
    };
@endphp

{{-- Wider than a phone on purpose: below 760px the labels would shrink past
     reading size, so the chart scrolls sideways inside its card instead. --}}
<div class="chart overflow-x-auto">
    <svg viewBox="0 0 {{ $width }} {{ $height }}" class="block h-auto w-full min-w-[760px]"
         role="img" aria-label="{{ $summary }}">
        {{-- Gridlines and y-axis numbers: quiet, so the columns lead. --}}
        @foreach ($ticks as $tick)
            <line x1="{{ $left }}" x2="{{ $width - $right }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}"
                  class="stroke-hairline" stroke-width="1" />
            <text x="{{ $left - 8 }}" y="{{ $y($tick) + 4 }}" text-anchor="end"
                  class="fill-muted text-[12.5px]">{{ number_format($tick) }}</text>
        @endforeach

        @foreach ($points as $i => $point)
            @php $x = $left + $i * $slot + ($slot - $barW) / 2; @endphp

            @if ($point['value'] > 0)
                {{-- The running hour/day is still filling up. Drawn pale with
                     a dashed edge so it does not read as a drop. --}}
                <path d="{{ $column($x, $point['value']) }}"
                      @class([
                          'fill-brand' => ! $point['partial'],
                          'fill-brand-tint stroke-brand' => $point['partial'],
                      ])
                      @if ($point['partial']) stroke-width="1.5" stroke-dasharray="3 3" @endif />
            @endif

            @if ($point['label'] !== null)
                <text x="{{ $x + $barW / 2 }}" y="{{ $height - 10 }}" text-anchor="middle"
                      class="fill-muted text-[12.5px]">{{ $point['label'] }}</text>
            @endif

            {{-- The whole slot, top to bottom, answers the pointer - a thin or
                 empty column is still easy to hover. --}}
            <rect x="{{ $left + $i * $slot }}" y="{{ $top }}" width="{{ $slot }}" height="{{ $plotH }}"
                  fill="transparent" data-tip="{{ $point['tip'] }}" />
        @endforeach

        <line x1="{{ $left }}" x2="{{ $width - $right }}" y1="{{ $base }}" y2="{{ $base }}"
              class="stroke-muted" stroke-width="1" stroke-opacity=".45" />
    </svg>

    {{-- The same numbers as text, for screen readers. --}}
    <table class="sr-only">
        <caption>{{ $summary }}</caption>
        <tbody>
            @foreach ($points as $point)
                <tr><td>{{ $point['tip'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
</div>
