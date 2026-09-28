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
     * receiving", the charts behind them, the shopping funnel and the
     * products drawing the most interest.
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

        // A product seen only in events sent before the tracker added names
        // has none; it is shown by its id instead.
        $topProducts = collect($stats['top_products'])->map(fn (array $p) => (object) [
            'id' => $p['product_id'],
            'brand' => $p['brand'],
            'name' => $p['name'] !== '' ? $p['name'] : 'Product #' . $p['product_id'],
            'clicks' => $p['clicks'],
            'added' => $p['added_to_cart'],
        ]);

        // Everything the time chart draws, for both ranges. It goes into the
        // page as JSON and resources/js/volume-chart.js draws it.
        $volume = [
            'hours' => $this->hourlySeries($stats['hourly'], $tz),
            'days' => $this->dailySeries($stats['daily']),
        ];

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
            'total', 'today', 'lastWeek', 'anonymousShare', 'lastReceived', 'topProducts',
            'volume', 'sparks', 'funnel', 'clock',
        ));
    }

    /**
     * The 24-hour view: one point per hour, this day against the one before.
     *
     * @param  list<array{start: string, events: int, previous: int}>  $hours
     * @return array{labels: list<string>, titles: list<string>, current: list<int>, previous: list<int>, currentLabel: string, previousLabel: string}
     */
    private function hourlySeries(array $hours, string $tz): array
    {
        $starts = collect($hours)->map(fn ($h) => Carbon::parse($h['start'])->setTimezone($tz));

        return [
            'labels' => $starts->map(fn ($start) => $start->format('H:i'))->all(),
            'titles' => $starts->map(fn ($start) => $start->format('H:i') . '–' . $start->copy()->addHour()->format('H:i'))->all(),
            'current' => array_column($hours, 'events'),
            'previous' => array_column($hours, 'previous'),
            'currentLabel' => 'Last 24 hours',
            'previousLabel' => 'The 24 hours before',
        ];
    }

    /**
     * The 14-day view: one point per day, against the 14 days before.
     *
     * @param  list<array{date: string, events: int, anonymous: int, previous: int}>  $days
     * @return array{labels: list<string>, titles: list<string>, current: list<int>, previous: list<int>, currentLabel: string, previousLabel: string}
     */
    private function dailySeries(array $days): array
    {
        $dates = collect($days)->map(fn ($d) => Carbon::parse($d['date']));

        return [
            'labels' => $dates->map(fn ($date) => $date->format('j M'))->all(),
            'titles' => $dates->map(fn ($date) => $date->format('D j M') . ' (vs ' . $date->copy()->subDays(14)->format('j M') . ')')->all(),
            'current' => array_column($days, 'events'),
            'previous' => array_column($days, 'previous'),
            'currentLabel' => 'Last 14 days',
            'previousLabel' => 'The 14 days before',
        ];
    }
}
