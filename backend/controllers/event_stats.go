package controllers

import (
	"event-api/config"
	"log"
	"time"

	"github.com/gin-gonic/gin"
)

type actionCount struct {
	Action string `db:"event_action" json:"action"`
	Count  int    `db:"total" json:"count"`
}

// One hour of the 24-hour chart. Start is the beginning of the hour in
// display time, e.g. "2026-09-28T14:00:00+03:00".
type hourBucket struct {
	Start  time.Time `json:"start"`
	Events int       `json:"events"`
}

// One day of the 14-day chart, as a display-time date ("2026-09-28").
type dayBucket struct {
	Date      string `json:"date"`
	Events    int    `json:"events"`
	Anonymous int    `json:"anonymous"`
}

type funnelStep struct {
	Step     string `json:"step"`
	Sessions int    `json:"sessions"`
}

// The numbers the panel's dashboard shows. Counts only: the panel turns
// counts into percentages itself, so this endpoint never has to decide how
// to round.
type eventStats struct {
	Total     int `db:"total" json:"total"`
	Today     int `db:"today" json:"today"`
	LastWeek  int `db:"last_7_days" json:"last_7_days"`
	Anonymous int `db:"anonymous" json:"anonymous"`
	// nil (JSON null) when the table is empty.
	LastEventAt *time.Time    `db:"last_event_at" json:"last_event_at"`
	ByAction    []actionCount `db:"-" json:"by_action"`

	// The clock "today", Hourly and Daily are counted in.
	Timezone string       `db:"-" json:"timezone"`
	Hourly   []hourBucket `db:"-" json:"hourly"`
	Daily    []dayBucket  `db:"-" json:"daily"`
	Funnel   []funnelStep `db:"-" json:"funnel"`
}

// EventStats answers GET /api/v1/events/stats.
func EventStats(c *gin.Context) {
	loc := config.DisplayLocation
	now := time.Now().In(loc)

	// "Today" starts at midnight on the display clock (Istanbul), not at UTC
	// midnight, which is 03:00 there. The time goes to MySQL as an argument,
	// so the definition lives here and nowhere else.
	y, m, d := now.Date()
	startOfToday := time.Date(y, m, d, 0, 0, 0, 0, loc)
	weekAgo := now.AddDate(0, 0, -7)

	// One pass over the table for all five numbers. COUNT(CASE WHEN ... THEN 1
	// END) counts only the rows where the condition holds, because COUNT
	// skips NULL and the CASE yields NULL otherwise.
	var s eventStats
	err := config.DB.Get(&s, `SELECT
			COUNT(*) AS total,
			COUNT(CASE WHEN event_timestamp >= ? THEN 1 END) AS today,
			COUNT(CASE WHEN event_timestamp >= ? THEN 1 END) AS last_7_days,
			COUNT(CASE WHEN user_id IS NULL THEN 1 END) AS anonymous,
			MAX(event_timestamp) AS last_event_at
		FROM events`, startOfToday, weekAgo)
	if err != nil {
		log.Println("event stats:", err)
		c.JSON(500, gin.H{"error": "could not read stats"})
		return
	}

	// Ties broken by name so two actions with the same count keep their
	// order between requests.
	s.ByAction = []actionCount{}
	err = config.DB.Select(&s.ByAction, `SELECT event_action, COUNT(*) AS total
		FROM events
		GROUP BY event_action
		ORDER BY total DESC, event_action ASC`)
	if err != nil {
		log.Println("event stats by action:", err)
		c.JSON(500, gin.H{"error": "could not read stats"})
		return
	}

	s.Timezone = loc.String()

	if s.Hourly, err = hourlySeries(now); err != nil {
		log.Println("event stats hourly:", err)
		c.JSON(500, gin.H{"error": "could not read stats"})
		return
	}
	if s.Daily, err = dailySeries(startOfToday); err != nil {
		log.Println("event stats daily:", err)
		c.JSON(500, gin.H{"error": "could not read stats"})
		return
	}
	if s.Funnel, err = funnelSteps(weekAgo); err != nil {
		log.Println("event stats funnel:", err)
		c.JSON(500, gin.H{"error": "could not read stats"})
		return
	}

	c.JSON(200, s)
}

// The display clock's offset from UTC as MySQL writes it, e.g. "+03:00".
// Taken from the current moment; Türkiye has no daylight saving time, so
// one offset is right for the whole window.
func mysqlOffset(t time.Time) string {
	return t.Format("-07:00")
}

// Events per hour for the last 24 hours, oldest first, the current (still
// running) hour last. Every hour is present: an hour with no events is a
// zero, not a gap, because an empty hour is exactly the thing a chart of a
// live system should make visible.
func hourlySeries(now time.Time) ([]hourBucket, error) {
	loc := now.Location()
	y, m, d := now.Date()
	currentHour := time.Date(y, m, d, now.Hour(), 0, 0, 0, loc)
	from := currentHour.Add(-23 * time.Hour)

	// CONVERT_TZ moves the stored UTC time onto the display clock before the
	// hour is cut off, so an event at 11:30 UTC lands in the 14:00 bucket.
	var rows []struct {
		Bucket string `db:"bucket"`
		Events int    `db:"events"`
	}
	err := config.DB.Select(&rows, `SELECT
			DATE_FORMAT(CONVERT_TZ(event_timestamp, '+00:00', ?), '%Y-%m-%d %H:00') AS bucket,
			COUNT(*) AS events
		FROM events
		WHERE event_timestamp >= ?
		GROUP BY bucket`, mysqlOffset(now), from)
	if err != nil {
		return nil, err
	}

	counts := make(map[string]int, len(rows))
	for _, r := range rows {
		counts[r.Bucket] = r.Events
	}

	series := make([]hourBucket, 0, 24)
	for i := 0; i < 24; i++ {
		start := from.Add(time.Duration(i) * time.Hour)
		series = append(series, hourBucket{Start: start, Events: counts[start.Format("2006-01-02 15:00")]})
	}
	return series, nil
}

// Events and anonymous events per day for the last 14 days including today,
// oldest first, with empty days filled in as zeros.
func dailySeries(startOfToday time.Time) ([]dayBucket, error) {
	from := startOfToday.AddDate(0, 0, -13)

	var rows []dayBucket
	err := config.DB.Select(&rows, `SELECT
			DATE_FORMAT(CONVERT_TZ(event_timestamp, '+00:00', ?), '%Y-%m-%d') AS date,
			COUNT(*) AS events,
			COUNT(CASE WHEN user_id IS NULL THEN 1 END) AS anonymous
		FROM events
		WHERE event_timestamp >= ?
		GROUP BY date`, mysqlOffset(startOfToday), from)
	if err != nil {
		return nil, err
	}

	byDate := make(map[string]dayBucket, len(rows))
	for _, r := range rows {
		byDate[r.Date] = r
	}

	series := make([]dayBucket, 0, 14)
	for i := 0; i < 14; i++ {
		date := from.AddDate(0, 0, i).Format("2006-01-02")
		day := byDate[date] // zero counts when the day had no events
		day.Date = date
		series = append(series, day)
	}
	return series, nil
}

// The shopping funnel since `since`: how many visits (session_id) reached
// each step, where a step counts only if every step before it happened too.
//
// Only events that carry a session_id take part - the market's. The inner
// query turns each session into four yes/no flags (did it ever do this?);
// the outer one counts sessions with step 1, with steps 1 and 2, and so on.
// It does not check that the steps happened in this order.
func funnelSteps(since time.Time) ([]funnelStep, error) {
	var f struct {
		Viewed  int `db:"viewed"`
		Clicked int `db:"clicked"`
		Added   int `db:"added"`
		Checked int `db:"checked"`
	}
	err := config.DB.Get(&f, `SELECT
			COUNT(CASE WHEN listed THEN 1 END) AS viewed,
			COUNT(CASE WHEN listed AND clicked THEN 1 END) AS clicked,
			COUNT(CASE WHEN listed AND clicked AND added THEN 1 END) AS added,
			COUNT(CASE WHEN listed AND clicked AND added AND checked_out THEN 1 END) AS checked
		FROM (
			SELECT
				JSON_UNQUOTE(JSON_EXTRACT(event_payload, '$.session_id')) AS session_id,
				MAX(event_action = 'page_view' AND event_source = 'listing') AS listed,
				MAX(event_action = 'product_click') AS clicked,
				MAX(event_action = 'add_to_cart') AS added,
				MAX(event_action = 'checkout_start') AS checked_out
			FROM events
			WHERE event_timestamp >= ?
				AND JSON_EXTRACT(event_payload, '$.session_id') IS NOT NULL
			GROUP BY session_id
		) AS per_session`, since)
	if err != nil {
		return nil, err
	}

	return []funnelStep{
		{Step: "viewed_listing", Sessions: f.Viewed},
		{Step: "clicked_product", Sessions: f.Clicked},
		{Step: "added_to_cart", Sessions: f.Added},
		{Step: "started_checkout", Sessions: f.Checked},
	}, nil
}
