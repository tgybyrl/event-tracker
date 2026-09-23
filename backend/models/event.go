package models

import (
	"encoding/json"
	"time"
)

type Event struct {
	EventID  string          `json:"event_id"`
	UserID   *int            `json:"user_id"`
	UserIP   *string         `json:"user_ip"`
	Platform string          `json:"event_platform"`
	Domain   string          `json:"event_domain"`
	Source   string          `json:"event_source"`
	Action   string          `json:"event_action"`
	Payload  json.RawMessage `json:"event_payload"`
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
