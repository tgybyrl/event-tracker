package config

import (
	"context"
	"log"
	"os"
	"time"

	"github.com/redis/go-redis/v9"
)

// Names shared by the API (which adds events) and cmd/worker (which reads
// them), so the two cannot drift apart.
const (
	// The stream POST /api/v1/events adds every accepted event to.
	EventStream = "events"
	// Where the worker moves an event it has given up on, so one bad message
	// cannot block the rest. Nothing reads it automatically.
	DeadStream = "events:dead"
	// The consumer group the workers read EventStream as. Redis remembers,
	// per group, which entries were handed out and which were acknowledged.
	IngestGroup = "ingest"
)

var Redis *redis.Client

func ConnectRedis() {
	addr := os.Getenv("REDIS_ADDR")
	if addr == "" {
		addr = "127.0.0.1:6379"
	}

	// NewClient does not connect yet; it only prepares a pool. The PING makes
	// a missing Redis stop the program at startup, the same way ConnectDB
	// does for MySQL, instead of surfacing on the first request.
	client := redis.NewClient(&redis.Options{Addr: addr})

	ctx, cancel := context.WithTimeout(context.Background(), 3*time.Second)
	defer cancel()
	if err := client.Ping(ctx).Err(); err != nil {
		log.Fatal("redis at ", addr, ": ", err)
	}
	Redis = client
}
