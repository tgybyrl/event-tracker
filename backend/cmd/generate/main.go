package main

import (
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"log"
	mrand "math/rand"
	"os"
	"time"

	"event-api/models"
)

var platforms = []string{"web", "app", "tablet"}
var domains = []string{"flo.com.tr", "flo.com"}
var source = []string{"listing", "detail", "cart"}

// How far back generated events may reach. Wide enough that the panel's date
// filter and "last 7 days" have something on both sides of the line.
const spread = 14 * 24 * time.Hour

var actions = []string{"product_click", "add_to_cart", "checkout_start"}
var actionPayloads = map[string]string{
	"product_click": `{
              "product_id": 553,
              "product_type": "Shoes",
              "category": "Running",
              "brand": "FLO"
      }`,
	"add_to_cart": `{
              "product_id": 553,
              "product_type": "Shoes",
              "quantity": 1,
              "price": 1299.90,
              "currency": "TRY"
      }`,
	"checkout_start": `{
              "cart_total": 2599.80,
              "item_count": 2,
              "currency": "TRY"
      }`,
}

// A random (version 4) UUID, by hand rather than through a UUID package: it
// is 16 random bytes with six bits pinned. Without those bits the string only
// looks like a UUID and any validator that checks the version rejects it.
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

func randomChoice(options []string) string {
	rand_choice := mrand.Intn(len(options))
	return options[rand_choice]
}

// A moment somewhere in the last `spread`, to the second - MySQL's TIMESTAMP
// keeps no fractions anyway.
func randomTimestamp() *time.Time {
	t := time.Now().UTC().Add(-time.Duration(mrand.Int63n(int64(spread)))).Truncate(time.Second)
	return &t
}

func randomEvent() models.Event {
	var userID *int
	var userIP *string

	if mrand.Intn(2) == 0 {
		// 1..1000: a user_id of 0 would read as a real user, not a missing one.
		id := mrand.Intn(1000) + 1
		userID = &id
	}

	if mrand.Intn(2) == 0 {
		ip := "127.0.0.1"
		userIP = &ip
	}

	action := randomChoice(actions)

	payloadJSON := payloadFor(action)

	return models.Event{
		EventID:   randomEventID(),
		UserID:    userID,
		UserIP:    userIP,
		Platform:  randomChoice(platforms),
		Domain:    randomChoice(domains),
		Source:    randomChoice(source),
		Action:    action,
		Payload:   json.RawMessage(payloadJSON),
		Timestamp: randomTimestamp(),
	}
}
func main() {
	count := 200

	events := make([]models.Event, 0, count)

	for i := 0; i < count; i++ {
		events = append(events, randomEvent())
	}
	data, err := json.MarshalIndent(events, "", "  ")
	// Stop on either error: carrying on after a failed marshal would write
	// an empty file over the last good dataset.
	if err != nil {
		log.Fatal("marshal events: ", err)
	}
	err = os.WriteFile("events.json", data, 0644)
	if err != nil {
		log.Fatal("write events.json: ", err)
	}
}

func payloadFor(action string) string {
	payload, ok := actionPayloads[action]
	if !ok {
		panic("no payload for action: " + action)
	}
	return payload
}
