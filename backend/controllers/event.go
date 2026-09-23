package controllers

import (
	"bytes"
	"errors"
	"event-api/config"
	"event-api/models"
	"fmt"
	"log"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/go-sql-driver/mysql"
)

// How far ahead of our clock a client's timestamp may be. A little slack for
// clocks that run fast; anything beyond that is a broken client, and a
// far-future row would sit at the top of every newest-first list forever.
const maxClockSkew = time.Minute

// MySQL's error number for a duplicate key — here, an event_id already stored.
const errDuplicateEntry = 1062

func CreateEvent(c *gin.Context) {

	var newEvent models.Event

	// Also runs the `binding` rules on models.Event: required fields, column
	// widths, UUID and IP formats.
	if err := c.ShouldBindJSON(&newEvent); err != nil {
		c.JSON(400, gin.H{"error": err.Error()})
		return
	}

	// Binding already proved the payload is valid JSON, but `[]`, `"x"` and
	// `null` are valid JSON too. The panel and every consumer expect an object.
	if !bytes.HasPrefix(bytes.TrimSpace(newEvent.Payload), []byte("{")) {
		c.JSON(400, gin.H{"error": "event_payload must be a JSON object"})
		return
	}

	if newEvent.Timestamp != nil && newEvent.Timestamp.After(time.Now().Add(maxClockSkew)) {
		c.JSON(400, gin.H{"error": "event_timestamp is in the future"})
		return
	}

	// A nil Timestamp is sent as NULL, and COALESCE turns that into the
	// database clock — same result the column DEFAULT gave before, without a
	// second query shape for "client sent no timestamp".
	query := `INSERT INTO events (event_id, user_id, user_ip, event_platform, event_domain, event_source, event_action, event_payload, event_timestamp) VALUES(?, ?, ?, ?, ?, ?, ?, ?, COALESCE(?, CURRENT_TIMESTAMP))`

	_, err := config.DB.Exec(query, newEvent.EventID, newEvent.UserID, newEvent.UserIP, newEvent.Platform, newEvent.Domain, newEvent.Source, newEvent.Action, newEvent.Payload, newEvent.Timestamp)

	if err != nil {
		// A resent event is the client's mistake, not ours: 409, not 500.
		var mysqlErr *mysql.MySQLError
		if errors.As(err, &mysqlErr) && mysqlErr.Number == errDuplicateEntry {
			c.JSON(409, gin.H{"error": "event_id already exists"})
			return
		}

		// The real error goes to our log. The client gets a generic message
		// instead of table and column names.
		log.Println("insert event:", err)
		c.JSON(500, gin.H{"error": "could not store event"})
		return
	}
	c.JSON(201, gin.H{"event_id": newEvent.EventID})

}

func SayHello(c *gin.Context) {
	name := c.QueryArray("name")
	fmt.Println(name)
	c.JSON(
		200, gin.H{"name": name})
}

func SimplePing(c *gin.Context) {
	c.JSON(
		200, gin.H{"message": "pong"})
}
