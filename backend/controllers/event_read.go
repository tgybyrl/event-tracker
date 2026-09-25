package controllers

import (
	"event-api/config"
	"event-api/models"
	"log"
	"strings"
	"time"

	"github.com/gin-gonic/gin"
)

// The query string GET /api/v1/events accepts. Same rules the panel's
// EventController enforced when it read the table itself: string filters
// capped at their column widths, dates as YYYY-MM-DD, and a fixed set of
// page sizes so nobody can ask for the whole table in one page.
type eventQuery struct {
	Platform string `form:"platform" binding:"omitempty,max=15"`
	Domain   string `form:"domain" binding:"omitempty,max=50"`
	Source   string `form:"source" binding:"omitempty,max=50"`
	Action   string `form:"action" binding:"omitempty,max=50"`
	From     string `form:"from" binding:"omitempty,datetime=2006-01-02"`
	To       string `form:"to" binding:"omitempty,datetime=2006-01-02"`
	Page     int    `form:"page" binding:"omitempty,min=1"`
	PerPage  int    `form:"per_page" binding:"omitempty,oneof=25 50 100"`
}

// ListEvents answers GET /api/v1/events: one page of events, newest first,
// narrowed by the filters in the query string, plus the numbers a pager
// needs.
func ListEvents(c *gin.Context) {
	var q eventQuery
	if err := c.ShouldBindQuery(&q); err != nil {
		c.JSON(400, gin.H{"error": err.Error()})
		return
	}

	// Binding already checked the format, so these cannot fail. Both are
	// zero-value times when the filter was not sent.
	from, _ := time.Parse(time.DateOnly, q.From)
	to, _ := time.Parse(time.DateOnly, q.To)
	if q.From != "" && q.To != "" && to.Before(from) {
		c.JSON(400, gin.H{"error": "to must be on or after from"})
		return
	}

	if q.Page == 0 {
		q.Page = 1
	}
	if q.PerPage == 0 {
		q.PerPage = 25
	}

	where, args := buildEventFilter(q, from, to)

	var total int
	if err := config.DB.Get(&total, "SELECT COUNT(*) FROM events"+where, args...); err != nil {
		log.Println("count events:", err)
		c.JSON(500, gin.H{"error": "could not read events"})
		return
	}

	// Initialised, not declared: an empty slice encodes as [] and a nil one
	// as null, and every client would have to special-case null.
	events := []models.Event{}

	// event_id breaks ties. Events that arrive together share a timestamp and
	// MySQL gives no stable order among equal values, so without it a row
	// could appear on page 1 and again on page 2.
	query := `SELECT event_id, user_id, user_ip, event_platform, event_domain, event_source, event_action, event_payload, event_timestamp
		FROM events` + where + `
		ORDER BY event_timestamp DESC, event_id ASC
		LIMIT ? OFFSET ?`
	pageArgs := append(args, q.PerPage, (q.Page-1)*q.PerPage)

	if err := config.DB.Select(&events, query, pageArgs...); err != nil {
		log.Println("list events:", err)
		c.JSON(500, gin.H{"error": "could not read events"})
		return
	}

	// Ceiling division; an empty result still has one (empty) page.
	lastPage := max(1, (total+q.PerPage-1)/q.PerPage)

	c.JSON(200, gin.H{
		"data": events,
		"meta": gin.H{
			"page":      q.Page,
			"per_page":  q.PerPage,
			"total":     total,
			"last_page": lastPage,
		},
	})
}

// buildEventFilter turns the filters that were actually sent into a WHERE
// clause and its arguments. A filter that was not sent adds nothing.
//
// Only fixed column names are ever written into the SQL text. Every value the
// client sent goes into args and reaches MySQL through a ? placeholder, so no
// filter value can change the shape of the query.
func buildEventFilter(q eventQuery, from, to time.Time) (string, []any) {
	var conds []string
	var args []any

	add := func(cond string, arg any) {
		conds = append(conds, cond)
		args = append(args, arg)
	}

	if q.Platform != "" {
		add("event_platform = ?", q.Platform)
	}
	if q.Domain != "" {
		add("event_domain = ?", q.Domain)
	}
	if q.Source != "" {
		add("event_source = ?", q.Source)
	}
	if q.Action != "" {
		add("event_action = ?", q.Action)
	}

	// `to` is inclusive of the whole day, so the bound is the start of the
	// next day. Comparing the plain column (not DATE(event_timestamp)) keeps
	// the condition usable by an index on event_timestamp, if one is added.
	if q.From != "" {
		add("event_timestamp >= ?", from)
	}
	if q.To != "" {
		add("event_timestamp < ?", to.AddDate(0, 0, 1))
	}

	if len(conds) == 0 {
		return "", nil
	}
	return " WHERE " + strings.Join(conds, " AND "), args
}

// The columns a client may ask for distinct values of, under the names the
// panel's filter form already uses. A fixed list, because a column name
// cannot travel as a ? argument — it has to be written into the SQL text,
// so it must never come from the request.
var facetColumns = []struct{ name, column string }{
	{"platform", "event_platform"},
	{"domain", "event_domain"},
	{"source", "event_source"},
	{"action", "event_action"},
}

// EventFacets answers GET /api/v1/events/facets: every value each filterable
// column currently holds, sorted. The panel builds its filter dropdowns from
// this, so a new action Go starts receiving appears there with no change in
// the panel.
func EventFacets(c *gin.Context) {
	out := gin.H{}

	for _, f := range facetColumns {
		values := []string{}
		query := "SELECT DISTINCT " + f.column + " FROM events ORDER BY " + f.column
		if err := config.DB.Select(&values, query); err != nil {
			log.Println("facet", f.column+":", err)
			c.JSON(500, gin.H{"error": "could not read facets"})
			return
		}
		out[f.name] = values
	}

	c.JSON(200, out)
}
