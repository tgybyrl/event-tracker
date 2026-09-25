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

// The numbers the panel's dashboard shows. Counts only: the panel turns
// anonymous/total into a percentage itself, so this endpoint never has to
// decide how to round.
type eventStats struct {
	Total     int `db:"total" json:"total"`
	Today     int `db:"today" json:"today"`
	LastWeek  int `db:"last_7_days" json:"last_7_days"`
	Anonymous int `db:"anonymous" json:"anonymous"`
	// nil (JSON null) when the table is empty.
	LastEventAt *time.Time    `db:"last_event_at" json:"last_event_at"`
	ByAction    []actionCount `db:"-" json:"by_action"`
}

// EventStats answers GET /api/v1/events/stats.
func EventStats(c *gin.Context) {
	// "Today" and "last 7 days" are measured from Go's clock in UTC, the same
	// zone every stored timestamp is in, and passed to MySQL as arguments, so
	// the definition of "today" lives here and nowhere else.
	now := time.Now().UTC()
	startOfToday := now.Truncate(24 * time.Hour)
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

	c.JSON(200, s)
}
