package main

import (
	"event-api/config"
	"event-api/controllers"
	"event-api/middleware"
	"log"
	"os"
	"strings"

	"github.com/gin-gonic/gin"
)

func main() {

	config.ConnectDB()

	// Fail closed: with no key configured the read endpoints would have
	// nothing to compare against, so refuse to start instead of serving them
	// open. (.env is already loaded by config's godotenv/autoload import.)
	apiKey := os.Getenv("EVENTS_API_KEY")
	if apiKey == "" {
		log.Fatal("EVENTS_API_KEY is not set; see backend/.env.example")
	}

	r := gin.Default()

	// Only the proxy in front of Go (Caddy) may say who the visitor is,
	// through the X-Forwarded-For header it adds. Any other sender's header
	// is ignored and c.ClientIP() falls back to the connection's address.
	// On Docker Desktop the proxy's requests arrive from 127.0.0.1.
	trusted := os.Getenv("TRUSTED_PROXIES")
	if trusted == "" {
		trusted = "127.0.0.1,::1"
	}
	if err := r.SetTrustedProxies(strings.Split(trusted, ",")); err != nil {
		log.Fatal("TRUSTED_PROXIES: ", err)
	}

	r.GET("/ping", controllers.SimplePing)

	r.GET("/hello", controllers.SayHello)

	// Everything clients and the panel use lives under one versioned prefix,
	// so a breaking change can later ship as /api/v2 next to this one.
	v1 := r.Group("/api/v1")

	// Public: trackers post events from browsers and apps.
	v1.POST("/events", controllers.CreateEvent)

	// Reads hand out every stored event, so they need the key.
	read := v1.Group("", middleware.RequireAPIKey(apiKey))
	read.GET("/events", controllers.ListEvents)
	read.GET("/events/facets", controllers.EventFacets)
	read.GET("/events/stats", controllers.EventStats)

	r.Run()
}
