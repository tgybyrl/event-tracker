{{--
    Phase A: the shell, rendered with no data behind it.
    The dashes are the honest "not connected yet" state — Phase B replaces
    them with counts from the events table.
--}}
<x-layouts.app title="Dashboard">
    <x-slot:actions>
        <x-button href="/events">View all events</x-button>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card pending label="Events today" value="—" note="Not connected yet" />
        <x-stat-card pending label="Events this week" value="—" note="Not connected yet" />
        <x-stat-card pending label="Known users" value="—" note="Not connected yet" />
        <x-stat-card pending label="Anonymous events" value="—" note="Not connected yet" />
    </div>

    <x-card class="mt-4">
        <x-empty-state title="No data source yet"
                       body="The events table already holds rows written by the Go service. Phase B adds the Eloquent model and controller that read them.">
            <x-slot:action>
                <x-button variant="primary" href="/events">Open events</x-button>
            </x-slot:action>
        </x-empty-state>
    </x-card>
</x-layouts.app>
