<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EventController extends Controller
{
    /**
     * List the events Go has written, newest first, narrowed by the toolbar.
     */
    public function index(Request $request): View
    {
        // A query string is user input like any other. `per_page` is the one
        // that can hurt: without the allowlist, ?per_page=999999 pulls the
        // whole table into a single page.
        $filters = $request->validate([
            'platform' => ['nullable', 'string', 'max:15'],
            'domain' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:50'],
            'action' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
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
        ];

        $events = Event::query()
            // when($value, $callback) runs the callback only if $value is
            // truthy — an unset filter adds no WHERE clause at all.
            ->when($filters['platform'], fn ($query, $value) => $query->where('event_platform', $value))
            ->when($filters['domain'], fn ($query, $value) => $query->where('event_domain', $value))
            ->when($filters['source'], fn ($query, $value) => $query->where('event_source', $value))
            ->when($filters['action'], fn ($query, $value) => $query->where('event_action', $value))
            // whereDate compares the date part only, so `to` includes every
            // event on that day instead of cutting off at midnight.
            ->when($filters['from'], fn ($query, $value) => $query->whereDate('event_timestamp', '>=', $value))
            ->when($filters['to'], fn ($query, $value) => $query->whereDate('event_timestamp', '<=', $value))
            ->orderByDesc('event_timestamp')
            // Tie-breaker. The seeded events land within the same second and
            // MySQL gives no stable order for ties, so without a second key a
            // row can show up on page 1 and again on page 2.
            ->orderBy('event_id')
            ->paginate($filters['per_page'])
            // Keeps ?platform=... and friends on the page links.
            ->withQueryString();

        $options = [
            'platform' => $this->distinctValues('event_platform'),
            'domain' => $this->distinctValues('event_domain'),
            'source' => $this->distinctValues('event_source'),
            'action' => $this->distinctValues('event_action'),
        ];

        return view('events.index', compact('events', 'options', 'filters'));
    }

    /**
     * The values a column actually holds, for one filter dropdown.
     *
     * Read from the table rather than hardcoded, so an action Go starts
     * sending shows up in the filter without a change here.
     *
     * @return Collection<int, string>
     */
    private function distinctValues(string $column): Collection
    {
        return Event::query()
            ->select($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column);
    }
}
