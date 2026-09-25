package main

import (
	"event-api/config"
	"event-api/controllers"

	"github.com/gin-gonic/gin"
)

func main() {

	config.ConnectDB()

	r := gin.Default()

	r.GET("/ping", controllers.SimplePing)

	r.GET("/hello", controllers.SayHello)

	// Everything clients and the panel use lives under one versioned prefix,
	// so a breaking change can later ship as /api/v2 next to this one.
	v1 := r.Group("/api/v1")

	// Public: trackers post events from browsers and apps.
	v1.POST("/events", controllers.CreateEvent)

	r.Run()
}
