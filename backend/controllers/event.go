package controllers

import (
	"event-api/models"
	"fmt"

	"github.com/gin-gonic/gin"
)

func CreateEvent(c *gin.Context) {
	
	var newEvent models.Event
	c.ShouldBindJSON(&newEvent)
	fmt.Println(newEvent)
	c.JSON(200, gin.H{ "message": "Event successfully reached", "data": newEvent})
	
}

func SayHello(c *gin.Context) {
		name := c.QueryArray("name") 
		fmt.Println(name)
		c.JSON(
			200, gin.H{"name": name})
}

func SimplePing(c *gin.Context) {
	c.JSON(
			200, gin.H{"message": "pong"})
}