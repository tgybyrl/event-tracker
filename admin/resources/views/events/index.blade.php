@php
    // Value -> badge tone. One hue per value so a column can be scanned by
    // colour alone. Anything unmapped falls back to slate inside <x-badge>,
    // so a new platform from Go shows up grey rather than breaking. Action
    // tones live in <x-action-badge>, because the dashboard shows them too.
    $platformTones = [
        'web' => 'blue',
        'app' => 'green',
        'tablet' => 'violet',
    ];

    $th = 'px-4 py-3 text-start text-xs font-bold uppercase tracking-wide text-muted whitespace-nowrap';
    $td = 'px-4 py-3 align-top text-sm whitespace-nowrap';

    $fieldLabel = 'mb-1.5 block text-xs font-bold uppercase tracking-wide text-muted';
    $field = 'w-full rounded-xl border border-hairline bg-surface px-3 py-2.5 text-sm font-semibold text-ink';

    // The four column filters, in toolbar order. Each one's <option> list
    // comes from $options, which the controller reads out of the table.
    $columnFilters = [
        'platform' => 'Platform',
        'domain' => 'Domain',
        'source' => 'Source',
        'action' => 'Action',
    ];

    // Is the list narrowed right now? Drives three things: the panel starts
    // open, the Clear button appears, and an empty result says "no matches"
    // instead of "no events yet". per_page is excluded — 50 rows a page is
    // not a filter.
    $isFiltered = array_filter([
        $filters['platform'],
        $filters['domain'],
        $filters['source'],
        $filters['action'],
        $filters['from'],
        $filters['to'],
    ]) !== [];

    // A rejected query string redirects back here, so the panel has to be
    // open for the message to be read.
    $panelOpen = $isFiltered || $errors->any();
@endphp

<x-layouts.app title="Events">
    <x-slot:actions>
        <x-button href="/">Back to dashboard</x-button>
    </x-slot:actions>

    <x-card>
        {{-- Hidden only when the table is genuinely empty: filter controls
             that can never match anything are just noise. --}}
        @if ($events->isNotEmpty() || $isFiltered)
            {{-- One GET form for both rows, so the page size and the filters
                 submit together and land in the query string the controller
                 validates. --}}
            <form method="GET" action="{{ route('events.index') }}">
                <div class="flex flex-wrap items-center gap-3 border-b border-hairline px-4 py-3">
                    <label class="flex items-center gap-2 text-[13px] text-muted">
                        Showing
                        {{-- Applies on change; the listener in app.js submits the form. --}}
                        <select id="per-page" name="per_page"
                                class="rounded-xl border border-hairline bg-surface px-2.5 py-1.5 text-sm font-bold text-ink">
                            @foreach ([25, 50, 100] as $size)
                                <option value="{{ $size }}" @selected($filters['per_page'] == $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                        per page
                    </label>

                    <x-button id="filter-toggle" class="ms-auto" aria-controls="filter-panel"
                              aria-expanded="{{ $panelOpen ? 'true' : 'false' }}">
                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor"
                             stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 5h16l-6 7v5l-4 2v-7z"/>
                        </svg>
                        Filter
                    </x-button>
                </div>

                <div id="filter-panel" @unless ($panelOpen) hidden @endunless
                     class="border-b border-hairline px-4 py-4">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($columnFilters as $name => $label)
                            <div>
                                <label for="filter-{{ $name }}" class="{{ $fieldLabel }}">{{ $label }}</label>
                                <select id="filter-{{ $name }}" name="{{ $name }}" class="{{ $field }}">
                                    <option value="">All {{ Str::lower($label) }}s</option>
                                    @foreach ($options[$name] as $value)
                                        <option value="{{ $value }}" @selected($filters[$name] === $value)>{{ $value }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach

                        <div>
                            <label for="filter-from" class="{{ $fieldLabel }}">From</label>
                            <input type="date" id="filter-from" name="from" value="{{ $filters['from'] }}"
                                   class="{{ $field }}">
                        </div>

                        <div>
                            <label for="filter-to" class="{{ $fieldLabel }}">To</label>
                            <input type="date" id="filter-to" name="to" value="{{ $filters['to'] }}"
                                   class="{{ $field }}">
                        </div>
                    </div>

                    @if ($errors->any())
                        <ul class="mt-3 space-y-1 text-sm font-semibold text-tag-rose-ink">
                            @foreach ($errors->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-4 flex flex-wrap items-center gap-2.5">
                        <x-button type="submit" variant="primary">Apply filters</x-button>

                        @if ($isFiltered)
                            <x-button :href="route('events.index')">Clear</x-button>
                        @endif
                    </div>
                </div>
            </form>
        @endif

        @if ($events->isEmpty())
            @if ($isFiltered)
                <x-empty-state title="No events match these filters"
                               body="Nothing in the table satisfies every filter at once. Widen one of them, or clear them all.">
                    <x-slot:action>
                        <x-button :href="route('events.index')">Clear filters</x-button>
                    </x-slot:action>
                </x-empty-state>
            @else
                <x-empty-state title="No events yet"
                               body="The table is empty. Start the Go service and run the send script to POST the generated dataset.">
                    <x-slot:action>
                        <x-badge tone="slate">go run ./cmd/send</x-badge>
                    </x-slot:action>
                </x-empty-state>
            @endif
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
                                    <x-action-badge :action="$event->event_action" />
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
