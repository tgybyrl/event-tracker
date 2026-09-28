@php
    $switchButton = 'rounded-lg px-3 py-1.5 text-[13px] font-bold text-muted transition-colors hover:text-ink aria-pressed:bg-surface aria-pressed:text-ink aria-pressed:shadow-card';
@endphp

<x-layouts.app title="Dashboard">
    <x-slot:actions>
        <x-button :href="route('events.index')">View all events</x-button>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Events today" :value="number_format($today)" :note="'Since 00:00 (' . $clock . ')'">
            <x-slot:chart><x-chart.sparkline :values="$sparks['today']" label="Events per hour today" /></x-slot:chart>
        </x-stat-card>

        <x-stat-card label="Last 7 days" :value="number_format($lastWeek)"
                     :note="number_format($total) . ' in total'">
            <x-slot:chart><x-chart.sparkline :values="$sparks['week']" label="Events per day, last 7 days" /></x-slot:chart>
        </x-stat-card>

        @if ($anonymousShare === null)
            <x-stat-card pending label="Anonymous share" value="—" note="No events yet" />
        @else
            <x-stat-card label="Anonymous share" :value="round($anonymousShare * 100) . '%'"
                         note="Events with no user_id">
                <x-slot:chart><x-chart.sparkline :values="$sparks['anonymous']" label="Anonymous share per day, last 7 days" /></x-slot:chart>
            </x-stat-card>
        @endif

        {{-- The one card that says something is broken: if this reads
             "3 days ago", nothing is arriving. --}}
        @if ($lastReceived === null)
            <x-stat-card pending label="Last event" value="—" note="Nothing received yet" />
        @else
            <x-stat-card label="Last event" :value="$lastReceived->diffForHumans(short: true)"
                         :note="$lastReceived->format('d M Y H:i:s') . ' (' . $clock . ')'" />
        @endif
    </div>

    {{-- Events over time. Both ranges are drawn on the server; the buttons
         only choose which one is visible. --}}
    <x-card class="mt-4">
        <div class="flex flex-wrap items-center gap-3 border-b border-hairline px-5 py-4">
            <div>
                <h2 class="text-base font-bold">Events over time</h2>
                <p class="mt-0.5 text-sm text-muted">{{ $clock }} time. The last column is still filling up.</p>
            </div>
            <div class="ms-auto flex rounded-xl bg-canvas p-1" role="group" aria-label="Range" data-range-switch="volume">
                <button type="button" class="{{ $switchButton }}" data-range="hours" aria-pressed="true">24 hours</button>
                <button type="button" class="{{ $switchButton }}" data-range="days" aria-pressed="false">14 days</button>
            </div>
        </div>

        <div class="px-3 pb-3 pt-4 sm:px-5" data-range-panel="hours" data-range-for="volume">
            <x-chart.columns :points="$hourly" summary="Events per hour, last 24 hours" />
        </div>
        <div class="px-3 pb-3 pt-4 sm:px-5" data-range-panel="days" data-range-for="volume" hidden>
            <x-chart.columns :points="$daily" summary="Events per day, last 14 days" />
        </div>
    </x-card>

    <div class="mt-4 grid items-start gap-4 xl:grid-cols-2">
        <x-card>
            <div class="border-b border-hairline px-5 py-4">
                <h2 class="text-base font-bold">Shopping funnel</h2>
                <p class="mt-0.5 text-sm text-muted">Shop visits in the last 7 days, and how far they got.</p>
            </div>

            @if ($funnel[0]['sessions'] === 0)
                <x-empty-state title="No shop visits this week"
                               body="The funnel counts visits to the demo shop. Open it and click around; each visit shows up here.">
                    <x-slot:action>
                        <x-button variant="primary" href="/market/list">Open the shop</x-button>
                    </x-slot:action>
                </x-empty-state>
            @else
                <x-chart.funnel :steps="$funnel" />
            @endif
        </x-card>

        <x-card>
            @if ($byAction->isEmpty())
                <x-empty-state title="No events yet"
                               body="The table is empty. Open the shop, or run the send script to post the generated dataset.">
                    <x-slot:action>
                        <x-badge tone="slate">go run ./cmd/send</x-badge>
                    </x-slot:action>
                </x-empty-state>
            @else
                <div class="border-b border-hairline px-5 py-4">
                    <h2 class="text-base font-bold">Events by action</h2>
                    <p class="mt-0.5 text-sm text-muted">What people actually do, across every event received.</p>
                </div>

                <ul>
                    @foreach ($byAction as $row)
                        @php $share = $row->total / $total * 100; @endphp

                        <li class="flex items-center gap-4 border-b border-hairline px-5 py-3.5 last:border-0">
                            <div class="w-36 shrink-0">
                                {{-- Links into the events list, already filtered. --}}
                                <a href="{{ route('events.index', ['action' => $row->event_action]) }}">
                                    <x-action-badge :action="$row->event_action" />
                                </a>
                            </div>

                            <div class="hidden h-2 flex-1 overflow-hidden rounded-full bg-canvas sm:block" aria-hidden="true">
                                <div class="h-full rounded-full bg-brand" style="width: {{ round($share, 1) }}%"></div>
                            </div>

                            <p class="ms-auto shrink-0 text-sm tabular-nums sm:ms-0 sm:w-28 sm:text-end">
                                <span class="font-bold">{{ number_format($row->total) }}</span>
                                <span class="text-muted">· {{ round($share) }}%</span>
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-layouts.app>
