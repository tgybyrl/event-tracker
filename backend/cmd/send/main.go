package main

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"

	"event-api/models"
)

const targetURL = "http://127.0.0.1:8080/event"

func main() {
	data, err := os.ReadFile("events.json")
	if err != nil {
		fmt.Println("Error reading events.json:", err)
		return
	}

	var events []models.Event
	if err := json.Unmarshal(data, &events); err != nil {
		fmt.Println("Error parsing events.json:", err)
		return
	}

	sent := 0
	failed := 0

	for i, event := range events {
		body, err := json.Marshal(event)
		if err != nil {
			fmt.Printf("[%d] %s marshal error: %v\n", i, event.EventID, err)
			failed++
			continue
		}

		resp, err := http.Post(targetURL, "application/json", bytes.NewReader(body))
		if err != nil {
			fmt.Printf("[%d] %s request error: %v\n", i, event.EventID, err)
			failed++
			continue
		}

		respBody, _ := io.ReadAll(resp.Body)
		resp.Body.Close()

		fmt.Printf("[%d] %s -> %d %s\n", i, event.EventID, resp.StatusCode, respBody)

		if resp.StatusCode >= 200 && resp.StatusCode < 300 {
			sent++
		} else {
			failed++
		}
	}

	fmt.Printf("\nDone. sent=%d failed=%d total=%d\n", sent, failed, len(events))
}
