<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\View\View;

class EventController extends Controller
{
    /**
     * List the events Go has written, newest first.
     */
    public function index(): View
    {
        $events = Event::query()
            ->orderByDesc('event_timestamp')
            // Tie-breaker. The seeded events land within the same second and
            // MySQL gives no stable order for ties, so without a second key a
            // row can show up on page 1 and again on page 2.
            ->orderBy('event_id')
            ->paginate(25)
            // Keeps ?platform=... and friends on the page links in phase C.
            ->withQueryString();

        return view('events.index', compact('events'));
    }
}
