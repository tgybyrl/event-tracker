{{-- Rendered by bootstrap/app.php when a call to the Go API fails: the service
     is down, too slow, or answered with an error. The panel has no other
     source of event data, so the honest page says which piece is missing. --}}
<x-layouts.app title="Events unavailable">
    <x-card>
        <x-empty-state title="The event service is not answering"
                       body="Event data comes from the Go API, and the last request to it failed. Check that it is running, then reload this page.">
            <x-slot:action>
                <x-badge tone="slate">cd backend &amp;&amp; go run ./cmd/api</x-badge>
            </x-slot:action>
        </x-empty-state>
    </x-card>
</x-layouts.app>
