{{-- Phase D stub. It exists so the manage-users Gate protects a real route and
     can be demonstrated; phase E fills it with the actual user CRUD. --}}
<x-layouts.app title="Users">
    <x-card>
        <x-empty-state title="User management is not built yet"
                       body="Phase E adds creating, editing and removing panel accounts here. Only managers can reach this screen.">
            <x-slot:action>
                <x-button :href="route('events.index')">Go to events</x-button>
            </x-slot:action>
        </x-empty-state>
    </x-card>
</x-layouts.app>
