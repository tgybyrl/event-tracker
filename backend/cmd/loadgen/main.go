package main

import (
	"bytes"
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"log"
	"math"
	"net/http"
	"slices"
	"sync"
	"time"

	"event-api/models"
)

// Every loadgen event carries this domain, so all of them can be found and
// deleted with one WHERE (see docs/COMMANDS.md). They also carry no
// session_id, so the funnel and top products never count them.
const loadgenDomain = "loadgen.test"

// What one request ended as. err is set when no HTTP answer came back at all
// (connection refused, timeout); then status is 0.
type result struct {
	latency time.Duration
	status  int
	err     error
}

func main() {
	rate := flag.Int("rate", 500, "requests per second")
	duration := flag.Duration("duration", 30*time.Second, "how long to send")
	url := flag.String("url", "http://127.0.0.1:8080/api/v1/events", "POST target")
	flag.Parse()

	if *rate <= 0 || *duration <= 0 {
		fmt.Println("-rate and -duration must be positive")
		return
	}

	// Go's default transport keeps at most 2 idle connections per host. At
	// hundreds of requests a second almost every request would then open a
	// new TCP connection, and we would be measuring connection setup instead
	// of the API. Keeping up to `rate` of them open lets requests reuse them.
	transport := http.DefaultTransport.(*http.Transport).Clone()
	transport.MaxIdleConns = *rate
	transport.MaxIdleConnsPerHost = *rate
	client := &http.Client{Transport: transport, Timeout: 10 * time.Second}

	// Every request runs in its own goroutine and sends its result here.
	// Only the collector below touches the `all` slice, so no two goroutines
	// ever write to it at once and no lock is needed.
	results := make(chan result, *rate)
	var all []result
	collected := make(chan struct{})
	go func() {
		for r := range results {
			all = append(all, r)
		}
		close(collected)
	}()

	// Open loop: a new request starts on every tick, whether or not the
	// earlier ones have answered. If the API slows down, requests pile up
	// and the latencies show it. A closed loop (N senders that each wait for
	// their answer before sending again) would quietly send fewer requests
	// when the API is slow, and hide exactly the slowness we want to see.
	ticker := time.NewTicker(time.Second / time.Duration(*rate))
	deadline := time.After(*duration)
	var inFlight sync.WaitGroup
	scheduled := 0

	fmt.Printf("sending %d/s for %s to %s\n", *rate, *duration, *url)
	start := time.Now()

send:
	for {
		select {
		case <-ticker.C:
			scheduled++
			inFlight.Add(1)
			go func(seq int) {
				defer inFlight.Done()
				results <- send(client, *url, seq)
			}(scheduled)
		case <-deadline:
			break send
		}
	}
	ticker.Stop()

	// Wait for the requests still on their way, then tell the collector
	// there is nothing more to read.
	inFlight.Wait()
	elapsed := time.Since(start)
	close(results)
	<-collected

	report(all, *rate, *duration, scheduled, elapsed)
}

// send posts one event and times it from just before the request until the
// whole answer has been read.
func send(client *http.Client, url string, seq int) result {
	event := models.Event{
		EventID:  randomEventID(),
		Platform: "web",
		Domain:   loadgenDomain,
		Source:   "loadgen",
		Action:   "page_view",
		Payload:  json.RawMessage(fmt.Sprintf(`{"seq": %d}`, seq)),
	}
	body, err := json.Marshal(event)
	if err != nil {
		return result{err: err}
	}

	start := time.Now()
	resp, err := client.Post(url, "application/json", bytes.NewReader(body))
	if err != nil {
		return result{latency: time.Since(start), err: err}
	}
	// The body has to be read to the end and closed, or the connection
	// cannot go back to the pool for the next request.
	io.Copy(io.Discard, resp.Body)
	resp.Body.Close()

	return result{latency: time.Since(start), status: resp.StatusCode}
}

func report(all []result, rate int, duration time.Duration, scheduled int, elapsed time.Duration) {
	statuses := map[int]int{}
	errCount := 0
	var firstErr error
	var latencies []time.Duration

	for _, r := range all {
		if r.err != nil {
			errCount++
			if firstErr == nil {
				firstErr = r.err
			}
			continue
		}
		statuses[r.status]++
		latencies = append(latencies, r.latency)
	}

	// The ticker drops ticks when the loop cannot keep up, so scheduled can
	// fall short of rate × duration. If it does, loadgen itself was the
	// bottleneck and the run says nothing about the API.
	expected := int(float64(rate) * duration.Seconds())
	fmt.Printf("\nscheduled %d of %d, finished %d in %s (%.0f/s achieved)\n",
		scheduled, expected, len(all), elapsed.Round(time.Millisecond),
		float64(len(all))/elapsed.Seconds())

	fmt.Print("status:")
	codes := make([]int, 0, len(statuses))
	for code := range statuses {
		codes = append(codes, code)
	}
	slices.Sort(codes)
	for _, code := range codes {
		fmt.Printf("  %d×%d", code, statuses[code])
	}
	fmt.Printf("   errors: %d\n", errCount)
	if firstErr != nil {
		fmt.Println("first error:", firstErr)
	}

	if len(latencies) == 0 {
		return
	}
	slices.Sort(latencies)
	fmt.Printf("latency: p50 %s  p95 %s  p99 %s  max %s\n",
		round(percentile(latencies, 0.50)),
		round(percentile(latencies, 0.95)),
		round(percentile(latencies, 0.99)),
		round(latencies[len(latencies)-1]))
}

// percentile returns the value below which a share p (0..1) of the sorted
// durations fall, by the nearest-rank method: the ceil(p·n)-th smallest.
// p95 = 12ms means 95% of the requests answered in 12ms or less.
func percentile(sorted []time.Duration, p float64) time.Duration {
	if len(sorted) == 0 {
		return 0
	}
	rank := int(math.Ceil(p * float64(len(sorted))))
	if rank < 1 {
		rank = 1
	}
	return sorted[rank-1]
}

func round(d time.Duration) time.Duration {
	return d.Round(100 * time.Microsecond)
}

// A random (version 4) UUID. Copied from cmd/generate: two `package main`
// programs cannot import each other, and one short function is not worth a
// shared package yet. Change both if either changes.
func randomEventID() string {
	key_id := make([]byte, 16)

	_, err := rand.Read(key_id)

	if err != nil {
		// A fallback here would mean zero bytes, i.e. the same id every time.
		log.Fatal("read random bytes: ", err)
	}

	key_id[6] = (key_id[6] & 0x0f) | 0x40 // version 4: the 13th hex digit is always 4
	key_id[8] = (key_id[8] & 0x3f) | 0x80 // RFC 4122 variant: the 17th is 8, 9, a or b

	encoded_key_id := hex.EncodeToString(key_id)

	return fmt.Sprintf("%v-%v-%v-%v-%v", encoded_key_id[0:8], encoded_key_id[8:12], encoded_key_id[12:16], encoded_key_id[16:20], encoded_key_id[20:32])
}
