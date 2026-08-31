package models

import "time"

type Event struct {
	E_ID      string      `json:"event_id"`
	U_ID      int         `json:"user_id"`   
	Platform  string      `json:"event_platform"`
	Domain    string      `json:"event_domain"`
	Source    string      `json:"event_source"`
	Action    string      `json:"event_action"`
	Location  string      `json:"event_location"`
	Payload   any         `json:"event_payload"`
	Timestamp time.Time   `json:"event_timestamp"`
}