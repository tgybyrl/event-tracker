@php
    // Value -> badge tone. One hue per value so a column can be scanned by
    // colour alone. Anything unmapped falls back to slate inside <x-badge>,
    // so a new platform or action from Go shows up grey rather than breaking.
    $platformTones = [
        'web' => 'blue',
        'app' => 'green',
        'tablet' => 'violet',
    ];

    $actionTones = [
        'product_click' => 'blue',
        'add_to_cart' => 'amber',
        'checkout_start' => 'green',
    ];

    $th = 'px-4 py-3 text-start text-xs font-bold uppercase tracking-wide text-muted whitespace-nowrap';
    $td = 'px-4 py-3 align-top text-sm whitespace-nowrap';
@endphp

<x-layouts.app title="Events">
    <x-slot:actions>
        <x-button href="/">Back to dashboard</x-button>
    </x-slot:actions>

    <x-card>
        @if ($events->isEmpty())
            <x-empty-state title="No events yet"
                           body="The table is empty. Start the Go service and run the send script to POST the generated dataset.">
                <x-slot:action>
                    <x-badge tone="slate">go run ./tools/send</x-badge>
                </x-slot:action>
            </x-empty-state>
        @else
            {{-- The table scrolls inside the card so the page body never does. --}}
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-hairline">
                            <th scope="col" class="{{ $th }}">Time</th>
                            <th scope="col" class="{{ $th }}">Platform</th>
                            <th scope="col" class="{{ $th }}">Domain</th>
                            <th scope="col" class="{{ $th }}">Source</th>
                            <th scope="col" class="{{ $th }}">Action</th>
                            <th scope="col" class="{{ $th }}">User</th>
                            <th scope="col" class="{{ $th }}">Payload</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($events as $event)
                            <tr class="border-b border-hairline last:border-0 hover:bg-canvas">
                                <td class="{{ $td }}" title="{{ $event->event_id }}">
                                    <span class="font-semibold">{{ $event->event_timestamp->format('d M Y H:i:s') }}</span>
                                    <span class="mt-0.5 block text-xs text-muted">{{ Str::limit($event->event_id, 8, '…') }}</span>
                                </td>

                                <td class="{{ $td }}">
                                    <x-badge :tone="$platformTones[$event->event_platform] ?? 'slate'">
                                        {{ $event->event_platform }}
                                    </x-badge>
                                </td>

                                <td class="{{ $td }} text-muted">{{ $event->event_domain }}</td>

                                <td class="{{ $td }}">{{ $event->event_source }}</td>

                                <td class="{{ $td }}">
                                    <x-badge :tone="$actionTones[$event->event_action] ?? 'slate'">
                                        {{ $event->event_action }}
                                    </x-badge>
                                </td>

                                <td class="{{ $td }}">
                                    @if ($event->user_id === null)
                                        <x-badge tone="slate">anonymous</x-badge>
                                    @else
                                        <span class="font-semibold">#{{ $event->user_id }}</span>
                                    @endif
                                    <span class="mt-0.5 block text-xs text-muted">{{ $event->user_ip ?? 'no ip' }}</span>
                                </td>

                                <td class="px-4 py-3 align-top text-sm">
                                    {{-- Native disclosure: no JavaScript needed for a row to expand. --}}
                                    <details class="group">
                                        <summary class="cursor-pointer list-none font-semibold text-brand select-none">
                                            <span class="group-open:hidden">Show</span>
                                            <span class="hidden group-open:inline">Hide</span>
                                        </summary>
                                        <pre class="mt-2 max-w-md overflow-x-auto rounded-xl bg-canvas p-3 text-xs leading-relaxed">{{ json_encode($event->event_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($events->hasPages())
                <div class="flex flex-wrap items-center gap-3 border-t border-hairline px-4 py-3">
                    <p class="text-[13px] text-muted">
                        Showing {{ $events->firstItem() }}–{{ $events->lastItem() }} of {{ $events->total() }}
                    </p>

                    <div class="ms-auto flex items-center gap-2.5">
                        @if ($events->onFirstPage())
                            <span class="rounded-xl border border-hairline px-4 py-2.5 text-sm font-bold text-hairline">Previous</span>
                        @else
                            <x-button :href="$events->previousPageUrl()" rel="prev">Previous</x-button>
                        @endif

                        @if ($events->hasMorePages())
                            <x-button variant="primary" :href="$events->nextPageUrl()" rel="next">Next</x-button>
                        @else
                            <span class="rounded-xl border border-hairline px-4 py-2.5 text-sm font-bold text-hairline">Next</span>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </x-card>
</x-layouts.app>
