package main

import (
	"event-api/config"
	"event-api/controllers"
	"event-api/middleware"
	"log"
	"os"

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
