package controllers

import (
	"event-api/config"
	"event-api/models"
	"fmt"

	"github.com/gin-gonic/gin"
)

func CreateEvent(c *gin.Context) {
	
	var newEvent models.Event

	if err := c.ShouldBindJSON(&newEvent); err != nil {
		c.JSON(400, gin.H{"error": err.Error()})
		return
	}
	
	query:=`INSERT INTO events (event_id, user_id, user_ip, event_platform, event_domain, event_source, event_action, event_payload) VALUES(?, ?, ?, ?, ?, ?, ?, ?)`

	_, err := config.DB.Exec(query, newEvent.EventID, newEvent.UserID, newEvent.UserIP, newEvent.Platform, newEvent.Domain, newEvent.Source, newEvent.Action, newEvent.Payload)

	if err != nil{
		c.JSON(500, gin.H{"error": err.Error()})
		return		
	}
	c.JSON(201, gin.H{"event_id": newEvent.EventID})

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