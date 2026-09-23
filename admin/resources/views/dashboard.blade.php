<x-layouts.app title="Dashboard">
    <x-slot:actions>
        <x-button href="/events">View all events</x-button>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Events today" :value="number_format($today)" note="Since 00:00 UTC" />

        <x-stat-card label="Last 7 days" :value="number_format($lastWeek)"
                     :note="number_format($total) . ' in total'" />

        @if ($anonymousShare === null)
            <x-stat-card pending label="Anonymous share" value="—" note="No events yet" />
        @else
            <x-stat-card label="Anonymous share" :value="round($anonymousShare * 100) . '%'"
                         note="Events with no user_id" />
        @endif

        {{-- The one card that says something is broken: if this reads
             "3 days ago", nothing is arriving. --}}
        @if ($lastReceived === null)
            <x-stat-card pending label="Last event" value="—" note="Nothing received yet" />
        @else
            <x-stat-card label="Last event" :value="$lastReceived->diffForHumans(short: true)"
                         :note="$lastReceived->format('d M Y H:i:s') . ' UTC'" />
        @endif
    </div>

    <x-card class="mt-4">
        @if ($byAction->isEmpty())
            <x-empty-state title="No events yet"
                           body="The table is empty. Start the Go service and run the send script to POST the generated dataset.">
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

                        {{-- A plain bar, not a chart library: one div per row. --}}
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
</x-layouts.app>
