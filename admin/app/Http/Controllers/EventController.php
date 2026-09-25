<?php

namespace App\Http\Controllers;

use App\Services\EventsApi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class EventController extends Controller
{
    /**
     * List the events Go has stored, newest first, narrowed by the toolbar.
     *
     * The rows come from GET /api/v1/events. Filtering, ordering and paging
     * all happen in Go; this method validates the form, forwards it, and
     * shapes the answer for the view.
     */
    public function index(Request $request, EventsApi $api): View
    {
        // Go validates the same query string again — Go is the real boundary.
        // This copy exists for the person at the form: a bad value comes back
        // as a message in the filter panel instead of an error page.
        $filters = $request->validate([
            'platform' => ['nullable', 'string', 'max:15'],
            'domain' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:50'],
            'action' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        // validate() returns only the keys that were actually in the URL.
        // `+=` fills in the missing ones without overwriting what is there, so
        // the view can read $filters['platform'] without isset() everywhere.
        $filters += [
            'platform' => null,
            'domain' => null,
            'source' => null,
            'action' => null,
            'from' => null,
            'to' => null,
            'per_page' => 25,
            'page' => 1,
        ];

        $response = $api->events($filters);

        // The view reads $event->event_timestamp->format(...), so the ISO
        // string from the JSON becomes a date object here. Everything else is
        // used as it arrives: event_payload is already a decoded array.
        $rows = collect($response['data'])->map(fn (array $row) => (object) [
            ...$row,
            'event_timestamp' => Carbon::parse($row['event_timestamp']),
        ]);

        // The same paginator class Eloquent's paginate() returns, built by
        // hand from Go's meta block, so the view's pager keeps working
        // unchanged. 'query' keeps ?platform=... and friends on the page
        // links.
        $events = new LengthAwarePaginator(
            $rows,
            $response['meta']['total'],
            $response['meta']['per_page'],
            $response['meta']['page'],
            ['path' => $request->url(), 'query' => $request->query()],
        );

        // Dropdown values come from the data, not a hardcoded list, so an
        // action Go starts receiving shows up in the filter with no change
        // here.
        $options = $api->facets();

        return view('events.index', compact('events', 'options', 'filters'));
    }
}
