<?php

namespace App\Http\Controllers;

use App\Services\EventsApi;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * Four numbers that answer "is the pipeline alive, and what is it
     * receiving", plus a count per action.
     *
     * All counts come from GET /api/v1/events/stats. "Today" and "last 7
     * days" are defined in Go (UTC); this method only turns counts into what
     * the cards display.
     */
    public function index(EventsApi $api): View
    {
        $stats = $api->stats();

        $total = $stats['total'];
        $today = $stats['today'];
        $lastWeek = $stats['last_7_days'];

        // A jump here means login tracking broke on a client, not that users
        // changed. Null when the table is empty: 0% would claim a measurement.
        $anonymousShare = $total > 0 ? $stats['anonymous'] / $total : null;

        $lastReceived = $stats['last_event_at'] !== null
            ? Carbon::parse($stats['last_event_at'])
            : null;

        // Renamed to the keys the view already reads.
        $byAction = collect($stats['by_action'])->map(fn (array $row) => (object) [
            'event_action' => $row['action'],
            'total' => $row['count'],
        ]);

        return view('dashboard', compact('total', 'today', 'lastWeek', 'anonymousShare', 'lastReceived', 'byAction'));
    }
}
