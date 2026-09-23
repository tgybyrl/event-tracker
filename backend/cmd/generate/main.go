package main

import (
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"fmt"
	mrand "math/rand"
	"os"

	"event-api/models"
)


var platforms = []string{"web", "app", "tablet"}
var domains = []string{"flo.com.tr", "flo.com"}
var source = []string{"listing", "detail", "cart"}
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


func randomEventID() string {
	key_id := make([]byte, 16)

	_, err := rand.Read(key_id)

	if err != nil {
		fmt.Println(err)
	}
	encoded_key_id := hex.EncodeToString(key_id)
	
	return fmt.Sprintf("%v-%v-%v-%v-%v", encoded_key_id[0:8],encoded_key_id[8:12], encoded_key_id[12:16], encoded_key_id[16:20], encoded_key_id[20:32])
}

func randomChoice(options []string) string{
	rand_choice := mrand.Intn(len(options))
	return options[rand_choice]
}

func randomEvent() models.Event {
	var userID *int
	var userIP *string

	if mrand.Intn(2) == 0 {
		id := mrand.Intn(1000)
		userID = &id
	}

	if mrand.Intn(2) == 0 {
		ip := "127.0.0.1"
		userIP = &ip
	}

	action := randomChoice(actions)


	payloadJSON := payloadFor(action)

	return models.Event{		
		EventID: randomEventID(),
		UserID: userID,
		UserIP: userIP,
		Platform: randomChoice(platforms),
		Domain: randomChoice(domains),
		Source: randomChoice(source),
		Action: action,
		Payload:json.RawMessage(payloadJSON),
	}
}
func main() {
	count := 20

	events := make([]models.Event, 0, count)

	for i := 0; i < count; i++ {
		events = append(events, randomEvent())
	}
	data, err := json.MarshalIndent(events, "", "  ")
	if err != nil {
		fmt.Println("Error getting data:", err)
	}
	err = os.WriteFile("events.json", data, 0644)
	if err != nil {
		fmt.Println("Error writing file:", err)
	}
}

func payloadFor(action string) string {
	payload, ok := actionPayloads[action]
	if !ok {
		panic("no payload for action: " + action)
	}
	return payload
}
