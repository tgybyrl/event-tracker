package main

import (
	"event-api/controllers"
	"github.com/gin-gonic/gin"
)

func main() {

	r := gin.Default()

	r.GET("/ping", controllers.SimplePing)

	r.GET("/hello", controllers.SayHello)

	r.POST("/event", controllers.CreateEvent)

	r.Run()
}