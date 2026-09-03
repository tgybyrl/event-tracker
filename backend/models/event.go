package models

import "time"

type Event struct {
	EventID      string      `json:"event_id"`
	UserID      *int        `json:"user_id"`
	UserIP    *string     `json:"user_ip"`   
	Platform  string      `json:"event_platform"`
	Domain    string      `json:"event_domain"`
	Source    string      `json:"event_source"`
	Action    string      `json:"event_action"`
	Payload   any         `json:"event_payload"`
	Timestamp time.Time   `json:"event_timestamp"`
}