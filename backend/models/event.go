package models

import (
	"encoding/json"
	"time"
)

// `binding` tags are checked by c.ShouldBindJSON. The max= values mirror the
// VARCHAR widths in db/schema.sql, so an oversized value is a 400 here rather
// than a truncation or an error from MySQL.
type Event struct {
	EventID  string          `json:"event_id" binding:"required,uuid"`
	UserID   *int            `json:"user_id"`
	UserIP   *string         `json:"user_ip" binding:"omitempty,ip"`
	Platform string          `json:"event_platform" binding:"required,max=15"`
	Domain   string          `json:"event_domain" binding:"required,max=50"`
	Source   string          `json:"event_source" binding:"required,max=50"`
	Action   string          `json:"event_action" binding:"required,max=50"`
	Payload  json.RawMessage `json:"event_payload" binding:"required"`
	// When the event happened on the client, which can be earlier than when
	// it reaches us (offline queues, batched mobile sends). A pointer so that
	// "not sent" is nil rather than year 0001; the INSERT falls back to the
	// database clock for nil.
	Timestamp *time.Time `json:"event_timestamp,omitempty"`
}

/* araştırılacak
func (e Event) process() {
	e.UserID
}
*/
