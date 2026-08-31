package main

import "github.com/gin-gonic/gin"
import "fmt"

func main() {

	r := gin.Default()

	r.GET("/ping", func(c *gin.Context) {
		c.JSON(
			200, gin.H{"message": "pong"})
	})

	r.GET("/hello", func(c *gin.Context){
		name := c.QueryArray("name") 
		fmt.Println(name)
		c.JSON(
			200, gin.H{"name": name})
	})

	r.Run()
}