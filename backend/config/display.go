package config

import (
	"log"
	"os"
	"time"

	// Embeds the time zone database in the binary, so "Europe/Istanbul"
	// resolves even in a minimal container image with no zone files. Part of
	// the standard library, not a new dependency.
	_ "time/tzdata"
)

// DisplayLocation is the clock the dashboard counts in: where "today"
// starts and which hour an event falls into. Stored timestamps stay UTC;
// this only decides how they are grouped for people to read.
var DisplayLocation *time.Location

func LoadDisplayLocation() {
	name := os.Getenv("DISPLAY_TIMEZONE")
	if name == "" {
		name = "Europe/Istanbul"
	}

	loc, err := time.LoadLocation(name)
	if err != nil {
		log.Fatal("DISPLAY_TIMEZONE: ", err)
	}
	DisplayLocation = loc
}
