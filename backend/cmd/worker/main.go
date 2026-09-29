package main

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"log"
	"os"
	"os/signal"
	"strings"
	"syscall"
	"time"

	"event-api/config"
	"event-api/models"

	"github.com/go-sql-driver/mysql"
	"github.com/redis/go-redis/v9"
)

const (
	// Entries asked for per read. One XACK then confirms the whole batch.
	batchSize = 100
	// How long a read waits for new entries before returning empty, so the
	// loop comes round to its other work even when nothing arrives.
	blockFor = 5 * time.Second
	// An entry handed out this long ago and still not acknowledged is taken
	// back and tried again (a failed insert, or a worker that crashed).
	claimIdle = 30 * time.Second
	// Tries before an entry is moved to config.DeadStream.
	maxDeliveries = 5
	// Pause between checks while MySQL or Redis does not answer.
	outageWait = 2 * time.Second
	// How often the worker prints what it has done.
	reportEvery = 10 * time.Second
)

// The API always sets event_timestamp before queueing. COALESCE only covers
// entries added by hand (redis-cli) without one.
const insertEvent = `INSERT INTO events (event_id, user_id, user_ip, event_platform, event_domain, event_source, event_action, event_payload, event_timestamp) VALUES(?, ?, ?, ?, ?, ?, ?, ?, COALESCE(?, CURRENT_TIMESTAMP))`

// MySQL's error number for a duplicate key — an event_id already stored.
const errDuplicateEntry = 1062

// What became of one entry.
type outcome int

const (
	stored     outcome = iota // written now or before: acknowledge it
	retryLater                // MySQL refused it: leave it pending, try again later
	giveUp                    // can never be stored: move it to the dead stream
	mysqlDown                 // MySQL did not answer: stop and wait for it
)

// decide turns the result of one INSERT into what to do with the entry.
func decide(err error) outcome {
	if err == nil {
		return stored
	}
	var mysqlErr *mysql.MySQLError
	if errors.As(err, &mysqlErr) {
		// The same event arrived twice, or a crash came between the insert
		// and the acknowledgement. Either way the row is there.
		if mysqlErr.Number == errDuplicateEntry {
			return stored
		}
		// MySQL answered and said no. Maybe a lock timeout that passes, maybe
		// a value it will never take; the delivery count tells them apart.
		return retryLater
	}
	// No answer from MySQL at all: the connection failed, not the entry.
	return mysqlDown
}

// Counts for the periodic report.
type tally struct {
	stored, retried, dead int
}

func main() {
	config.ConnectDB()
	config.ConnectRedis()

	// Ctrl+C (SIGINT) or SIGTERM, which Docker sends on stop, cancels ctx.
	// The loop checks it between batches, so a batch that has begun is
	// written and acknowledged before the worker exits. After the first
	// signal stop() hands signals back to Go's default, so a second Ctrl+C
	// ends the program at once.
	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()
	go func() {
		<-ctx.Done()
		stop()
	}()

	createGroup()
	consumer := consumerName()
	log.Printf("worker %s: reading stream %q as group %q", consumer, config.EventStream, config.IngestGroup)

	var total tally
	var lastClaim time.Time // zero, so the first round retries what a previous run left
	lastReport := time.Now()

	for ctx.Err() == nil {
		if time.Since(lastReport) >= reportEvery {
			if total != (tally{}) {
				log.Printf("last %s: %d stored, %d left for retry, %d dead", reportEvery, total.stored, total.retried, total.dead)
			}
			total = tally{}
			lastReport = time.Now()
		}

		// While MySQL is down, take nothing from Redis. Entries only count a
		// delivery when they are handed out, so a long MySQL outage cannot
		// push good events over maxDeliveries and into the dead stream.
		if err := config.DB.PingContext(ctx); err != nil {
			if ctx.Err() == nil {
				log.Println("mysql not answering, waiting:", err)
				wait(ctx, outageWait)
			}
			continue
		}

		if time.Since(lastClaim) >= claimIdle {
			t, ok := retryStuck(consumer)
			total.add(t)
			lastClaim = time.Now()
			if !ok {
				continue
			}
		}

		msgs, err := read(ctx, consumer)
		if err != nil {
			if ctx.Err() == nil {
				log.Println("redis read failed, waiting:", err)
				wait(ctx, outageWait)
			}
			continue
		}
		t, _ := process(msgs)
		total.add(t)
	}
	log.Println("stopped")
}

// createGroup makes the consumer group, and the stream if the API has not
// made it yet. "0" means the group starts at the beginning of the stream,
// so events queued before any worker ever ran are not skipped.
func createGroup() {
	err := config.Redis.XGroupCreateMkStream(context.Background(), config.EventStream, config.IngestGroup, "0").Err()
	// BUSYGROUP: the group exists from an earlier run. That is the normal case.
	if err != nil && !strings.HasPrefix(err.Error(), "BUSYGROUP") {
		log.Fatal("create consumer group: ", err)
	}
}

// Redis tracks which consumer holds which entry, so each running worker
// needs its own name. Host and process id make two workers on one machine
// different.
func consumerName() string {
	host, _ := os.Hostname()
	return fmt.Sprintf("%s-%d", host, os.Getpid())
}

// read returns up to batchSize entries no consumer of the group has seen,
// waiting up to blockFor for them. None arriving is not an error.
func read(ctx context.Context, consumer string) ([]redis.XMessage, error) {
	streams, err := config.Redis.XReadGroup(ctx, &redis.XReadGroupArgs{
		Group:    config.IngestGroup,
		Consumer: consumer,
		Streams:  []string{config.EventStream, ">"}, // ">": entries never delivered
		Count:    batchSize,
		Block:    blockFor,
	}).Result()
	if errors.Is(err, redis.Nil) {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	return streams[0].Messages, nil
}

// process stores a batch and acknowledges what was stored. It deliberately
// does not take the signal context: once a batch has been read, it is
// finished even if Ctrl+C arrives meanwhile. ok is false when MySQL stopped
// answering part-way; the rest of the batch stays pending for a later retry.
func process(msgs []redis.XMessage) (t tally, ok bool) {
	ctx := context.Background()
	var done []string
	ok = true

	for _, m := range msgs {
		o := store(ctx, m)
		if o == mysqlDown {
			ok = false
			break
		}
		switch o {
		case stored:
			done = append(done, m.ID)
			t.stored++
		case retryLater:
			t.retried++
		case giveUp:
			if bury(ctx, m, "not a valid event") {
				done = append(done, m.ID)
				t.dead++
			}
		}
	}

	ack(ctx, done)
	return t, ok
}

// store inserts one entry and says what to do with it.
func store(ctx context.Context, m redis.XMessage) outcome {
	raw, isString := m.Values["event"].(string)
	var e models.Event
	if !isString || json.Unmarshal([]byte(raw), &e) != nil {
		return giveUp
	}

	_, err := config.DB.ExecContext(ctx, insertEvent, e.EventID, e.UserID, e.UserIP, e.Platform, e.Domain, e.Source, e.Action, e.Payload, e.Timestamp)
	o := decide(err)
	if o == retryLater {
		log.Printf("entry %s (event %s): %v", m.ID, e.EventID, err)
	}
	return o
}

// retryStuck takes back entries that have been pending longer than
// claimIdle — whoever held them — and tries them again, or gives up on
// those already delivered maxDeliveries times. ok is false when MySQL
// stopped answering.
func retryStuck(consumer string) (t tally, ok bool) {
	ctx := context.Background()

	// XPENDING lists the entries handed out and never acknowledged, with how
	// many times each has been delivered.
	pending, err := config.Redis.XPendingExt(ctx, &redis.XPendingExtArgs{
		Stream: config.EventStream,
		Group:  config.IngestGroup,
		Idle:   claimIdle,
		Start:  "-",
		End:    "+",
		Count:  batchSize,
	}).Result()
	if err != nil || len(pending) == 0 {
		return t, true
	}

	deliveries := map[string]int64{}
	ids := make([]string, len(pending))
	for i, p := range pending {
		deliveries[p.ID] = p.RetryCount
		ids[i] = p.ID
	}

	// XCLAIM makes this worker the holder and returns the entries' content.
	// MinIdle again, so an entry another worker has just picked up is left
	// alone.
	msgs, err := config.Redis.XClaim(ctx, &redis.XClaimArgs{
		Stream:   config.EventStream,
		Group:    config.IngestGroup,
		Consumer: consumer,
		MinIdle:  claimIdle,
		Messages: ids,
	}).Result()
	if err != nil {
		log.Println("claim stuck entries:", err)
		return t, true
	}

	var retry []redis.XMessage
	var buried []string
	for _, m := range msgs {
		if n := deliveries[m.ID]; n >= maxDeliveries {
			if bury(ctx, m, fmt.Sprintf("failed %d deliveries", n)) {
				buried = append(buried, m.ID)
				t.dead++
			}
			continue
		}
		retry = append(retry, m)
	}
	ack(ctx, buried)

	rt, ok := process(retry)
	t.add(rt)
	return t, ok
}

// bury copies an entry to the dead stream with the reason, so it can be
// looked at later. The caller acknowledges it only if the copy was made.
func bury(ctx context.Context, m redis.XMessage, reason string) bool {
	err := config.Redis.XAdd(ctx, &redis.XAddArgs{
		Stream: config.DeadStream,
		Values: map[string]any{"id": m.ID, "reason": reason, "event": m.Values["event"]},
	}).Err()
	if err != nil {
		log.Printf("move %s to %s: %v", m.ID, config.DeadStream, err)
		return false
	}
	log.Printf("entry %s moved to %s: %s", m.ID, config.DeadStream, reason)
	return true
}

// ack tells Redis these entries are done; they leave the pending list. If
// this fails the entries are delivered again later, find their rows
// already there (duplicate key) and are acknowledged then.
func ack(ctx context.Context, ids []string) {
	if len(ids) == 0 {
		return
	}
	if err := config.Redis.XAck(ctx, config.EventStream, config.IngestGroup, ids...).Err(); err != nil {
		log.Println("ack:", err)
	}
}

// wait pauses for d, or less if the worker is told to stop.
func wait(ctx context.Context, d time.Duration) {
	select {
	case <-ctx.Done():
	case <-time.After(d):
	}
}

func (t *tally) add(o tally) {
	t.stored += o.stored
	t.retried += o.retried
	t.dead += o.dead
}
