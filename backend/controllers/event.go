package controllers

import (
	"event-api/config"
	"event-api/models"
	"fmt"

	"github.com/gin-gonic/gin"
)

func CreateEvent(c *gin.Context) {
	
	var newEvent models.Event

	c.ShouldBindJSON(&newEvent)
	
	fmt.Println(newEvent)
	
	query:=`INSERT INTO events (event_id, user_id, user_ip, event_platform, event_domain, event_source, event_action, event_payload, event_timestamp) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)`

	_, err := config.DB.Exec(query, newEvent.EventID, newEvent.UserID, newEvent.UserIP, newEvent.Platform, newEvent.Domain, newEvent.Source, newEvent.Action, newEvent.Payload, newEvent.Timestamp)

	if err != nil{
		c.JSON(500, gin.H{"error": err.Error()})
		}
	
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