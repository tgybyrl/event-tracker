<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * Four numbers that answer "is the pipeline alive, and what is it
     * receiving", plus a count per action.
     *
     * Five small queries rather than one clever one: each card reads as its
     * own question. On a table with no indexes every one is a full scan —
     * fine at a few hundred rows, logged under Demo shortcuts in PLAN.md.
     */
    public function index(): View
    {
        $total = Event::query()->count();

        // "Today" is the UTC day: MySQL, the Go driver and Laravel all run in
        // UTC. Between 00:00 and 03:00 Istanbul time this still counts
        // yesterday's evening.
        $today = Event::query()->whereDate('event_timestamp', today())->count();

        // A rolling seven days, not "since Monday": the number should not
        // drop to near zero every Monday morning.
        $lastWeek = Event::query()->where('event_timestamp', '>=', now()->subDays(7))->count();

        // A jump here means login tracking broke on a client, not that users
        // changed. Null when the table is empty: 0% would claim a measurement.
        $anonymousShare = $total > 0
            ? Event::query()->whereNull('user_id')->count() / $total
            : null;

        // max() returns the raw column string (or null), not a cast attribute.
        $lastReceived = Event::query()->max('event_timestamp');
        $lastReceived = $lastReceived ? Carbon::parse($lastReceived) : null;

        $byAction = Event::query()
            ->select('event_action')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('event_action')
            ->orderByDesc('total')
            // Tie-break so two actions with the same count keep their order
            // between page loads.
            ->orderBy('event_action')
            ->get();

        return view('dashboard', compact('total', 'today', 'lastWeek', 'anonymousShare', 'lastReceived', 'byAction'));
    }
}
