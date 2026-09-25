<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * The panel's only way to reach event data: the Go API's read endpoints.
 *
 * The panel no longer queries the `events` table. Go owns it, and whatever
 * Go changes about the table, the panel only sees the JSON these three
 * endpoints return.
 *
 * One class rather than inline calls because both EventController and
 * DashboardController need the same base URL, key, timeout and failure
 * behaviour. A failed call throws — an HttpClientException, which
 * bootstrap/app.php turns into the "event service is not answering" page.
 */
class EventsApi
{
    /**
     * One page of events. $query holds the filters plus page and per_page;
     * null values are dropped so an unset filter is not sent at all.
     *
     * @param  array<string, mixed>  $query
     * @return array{data: array<int, array<string, mixed>>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function events(array $query): array
    {
        return $this->get('/events', array_filter($query, fn ($value) => $value !== null));
    }

    /**
     * Every value each filterable column holds, for the filter dropdowns.
     *
     * @return array{platform: list<string>, domain: list<string>, source: list<string>, action: list<string>}
     */
    public function facets(): array
    {
        return $this->get('/events/facets');
    }

    /**
     * The dashboard's counts.
     *
     * @return array{total: int, today: int, last_7_days: int, anonymous: int, last_event_at: ?string, by_action: list<array{action: string, count: int}>}
     */
    public function stats(): array
    {
        return $this->get('/events/stats');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        return Http::baseUrl(config('services.events_api.url'))
            // Sent as "Authorization: Bearer <key>", which is what Go's
            // RequireAPIKey middleware checks.
            ->withToken(config('services.events_api.key'))
            ->acceptJson()
            // Five seconds, not the default thirty: a page that hangs half a
            // minute on a dead service is worse than an error page at once.
            ->timeout(5)
            ->get($path, $query)
            // Any 4xx/5xx from Go becomes an exception instead of a response
            // the controllers would have to remember to check.
            ->throw()
            ->json();
    }
}
