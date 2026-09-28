<?php

namespace App\Http\Controllers;

use App\Services\EventsApi;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    /**
     * The funnel's steps, in order, as Go names them => as the panel shows
     * them.
     */
    private const FUNNEL_LABELS = [
        'viewed_listing' => 'Viewed the listing',
        'clicked_product' => 'Clicked a product',
        'added_to_cart' => 'Added to cart',
        'started_checkout' => 'Started checkout',
    ];

    /**
     * Four numbers that answer "is the pipeline alive, and what is it
     * receiving", the charts behind them, and a count per action.
     *
     * Everything comes from GET /api/v1/events/stats. Go decides the clock
     * (the display time zone) and the buckets; this method only turns them
     * into labels, tooltips and chart points.
     */
    public function index(EventsApi $api): View
    {
        $stats = $api->stats();
        $tz = $stats['timezone'];

        $total = $stats['total'];
        $today = $stats['today'];
        $lastWeek = $stats['last_7_days'];

        // A jump here means login tracking broke on a client, not that users
        // changed. Null when the table is empty: 0% would claim a measurement.
        $anonymousShare = $total > 0 ? $stats['anonymous'] / $total : null;

        $lastReceived = $stats['last_event_at'] !== null
            ? Carbon::parse($stats['last_event_at'])->setTimezone($tz)
            : null;

        // Renamed to the keys the view already reads.
        $byAction = collect($stats['by_action'])->map(fn (array $row) => (object) [
            'event_action' => $row['action'],
            'total' => $row['count'],
        ]);

        $hourly = $this->hourlyPoints($stats['hourly'], $tz);
        $daily = $this->dailyPoints($stats['daily']);

        // Sparklines. Today's line only covers hours since midnight; the
        // anonymous share skips days with no events, which have no share.
        $todayHours = collect($stats['hourly'])
            ->filter(fn ($h) => Carbon::parse($h['start'])->setTimezone($tz)->isSameDay(now($tz)))
            ->pluck('events')->values()->all();
        $lastSevenDays = collect($stats['daily'])->take(-7);
        $sparks = [
            'today' => $todayHours,
            'week' => $lastSevenDays->pluck('events')->all(),
            'anonymous' => $lastSevenDays->filter(fn ($d) => $d['events'] > 0)
                ->map(fn ($d) => round($d['anonymous'] / $d['events'] * 100))->values()->all(),
        ];

        $funnel = collect($stats['funnel'])->map(fn (array $step) => [
            'label' => self::FUNNEL_LABELS[$step['step']] ?? $step['step'],
            'sessions' => $step['sessions'],
        ])->all();

        // "Europe/Istanbul" -> "Istanbul", for notes like "since 00:00 (Istanbul)".
        $clock = Str::of($tz)->afterLast('/')->replace('_', ' ')->toString();

        return view('dashboard', compact(
            'total', 'today', 'lastWeek', 'anonymousShare', 'lastReceived', 'byAction',
            'hourly', 'daily', 'sparks', 'funnel', 'clock',
        ));
    }

    /**
     * 24 hourly buckets -> chart points. Every third hour is labelled,
     * counted back from the current one, so "now" always has a label.
     *
     * @param  list<array{start: string, events: int}>  $hours
     * @return list<array{label: ?string, value: int, tip: string, partial: bool}>
     */
    private function hourlyPoints(array $hours, string $tz): array
    {
        $last = count($hours) - 1;

        return collect($hours)->map(function (array $hour, int $i) use ($last, $tz) {
            $start = Carbon::parse($hour['start'])->setTimezone($tz);
            $partial = $i === $last;

            return [
                'label' => ($last - $i) % 3 === 0 ? $start->format('H:i') : null,
                'value' => $hour['events'],
                'tip' => $start->format('H:i') . '–' . $start->copy()->addHour()->format('H:i')
                    . ' · ' . $this->events($hour['events']) . ($partial ? ' so far' : ''),
                'partial' => $partial,
            ];
        })->all();
    }

    /**
     * 14 daily buckets -> chart points, every other day labelled, counted
     * back from today.
     *
     * @param  list<array{date: string, events: int, anonymous: int}>  $days
     * @return list<array{label: ?string, value: int, tip: string, partial: bool}>
     */
    private function dailyPoints(array $days): array
    {
        $last = count($days) - 1;

        return collect($days)->map(function (array $day, int $i) use ($last) {
            $date = Carbon::parse($day['date']);
            $partial = $i === $last;

            return [
                'label' => ($last - $i) % 2 === 0 ? $date->format('j M') : null,
                'value' => $day['events'],
                'tip' => $date->format('D j M') . ' · ' . $this->events($day['events']) . ($partial ? ' so far' : ''),
                'partial' => $partial,
            ];
        })->all();
    }

    private function events(int $count): string
    {
        return number_format($count) . ' ' . Str::plural('event', $count);
    }
}
