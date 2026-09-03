package main

import (
	"event-api/controllers"
	"event-api/config"
	"github.com/gin-gonic/gin"
)

func main() {

	config.ConnectDB()

	r := gin.Default()

	r.GET("/ping", controllers.SimplePing)

	r.GET("/hello", controllers.SayHello)

	r.POST("/event", controllers.CreateEvent)

	r.Run()
}