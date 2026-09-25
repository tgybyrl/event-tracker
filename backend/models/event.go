package models

import (
	"encoding/json"
	"time"
)

// `binding` tags are checked by c.ShouldBindJSON. The max= values mirror the
// VARCHAR widths in db/schema.sql, so an oversized value is a 400 here rather
// than a truncation or an error from MySQL.
//
// `db` tags name the column each field is read from when sqlx scans a row
// (GET /api/v1/events). Without them sqlx would look for columns called
// "eventid", "platform" and so on, which do not exist.
type Event struct {
	EventID  string          `json:"event_id" db:"event_id" binding:"required,uuid"`
	UserID   *int            `json:"user_id" db:"user_id"`
	UserIP   *string         `json:"user_ip" db:"user_ip" binding:"omitempty,ip"`
	Platform string          `json:"event_platform" db:"event_platform" binding:"required,max=15"`
	Domain   string          `json:"event_domain" db:"event_domain" binding:"required,max=50"`
	Source   string          `json:"event_source" db:"event_source" binding:"required,max=50"`
	Action   string          `json:"event_action" db:"event_action" binding:"required,max=50"`
	Payload  json.RawMessage `json:"event_payload" db:"event_payload" binding:"required"`
	// When the event happened on the client, which can be earlier than when
	// it reaches us (offline queues, batched mobile sends). A pointer so that
	// "not sent" is nil rather than year 0001; the INSERT falls back to the
	// database clock for nil.
	Timestamp *time.Time `json:"event_timestamp,omitempty" db:"event_timestamp"`
}

/* araştırılacak
func (e Event) process() {
	e.UserID
}
*/
