package controllers

import (
	"bytes"
	"encoding/json"
	"event-api/config"
	"event-api/models"
	"fmt"
	"log"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/redis/go-redis/v9"
)

// How far ahead of our clock a client's timestamp may be. A little slack for
// clocks that run fast; anything beyond that is a broken client, and a
// far-future row would sit at the top of every newest-first list forever.
const maxClockSkew = time.Minute

// Roughly how many entries the event stream keeps. Only entries every
// consumer group has acknowledged are trimmed (ACKED), so events the worker
// has not written yet are never dropped to stay under it.
const streamMaxLen = 100000

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

	// A browser cannot know its own public address, so the market's tracker
	// sends none and the server takes it from the connection (through the
	// proxy's X-Forwarded-For, see SetTrustedProxies in cmd/api). A value in
	// the body still wins: server-to-server senders post on behalf of someone
	// else and put that person's address there.
	if newEvent.UserIP == nil {
		if ip := c.ClientIP(); ip != "" {
			newEvent.UserIP = &ip
		}
	}

	// No timestamp from the client means "now". It has to be set here: the
	// row is written later by cmd/worker, and the database clock would record
	// when the worker got to the event, not when it arrived. Whole seconds,
	// like the column.
	if newEvent.Timestamp == nil {
		now := time.Now().UTC().Truncate(time.Second)
		newEvent.Timestamp = &now
	}

	body, err := json.Marshal(newEvent)
	if err != nil {
		log.Println("encode event:", err)
		c.JSON(500, gin.H{"error": "could not queue event"})
		return
	}

	// The event goes onto the Redis stream as one field holding its JSON;
	// cmd/worker reads it from there and inserts it into MySQL.
	err = config.Redis.XAdd(c.Request.Context(), &redis.XAddArgs{
		Stream: config.EventStream,
		Mode:   "ACKED",
		MaxLen: streamMaxLen,
		Approx: true,
		Values: map[string]any{"event": body},
	}).Err()
	if err != nil {
		// No queue, no event: 503 tells the client to try again later. There
		// is no fallback to inserting directly — two write paths would be
		// twice the ways to break.
		log.Println("queue event:", err)
		c.JSON(503, gin.H{"error": "queue unavailable"})
		return
	}

	// 202 Accepted: taken, not stored yet. A duplicate event_id can no longer
	// be refused here (the insert happens after this answer); the worker
	// finds the row already there and drops the copy.
	c.JSON(202, gin.H{"event_id": newEvent.EventID})

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
