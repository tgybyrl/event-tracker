package config

import (
	"fmt"
	"log"
	"os"

	_ "github.com/go-sql-driver/mysql"
	"github.com/jmoiron/sqlx"
	_ "github.com/joho/godotenv/autoload"
)

var DB *sqlx.DB

func ConnectDB() {
	dsn := fmt.Sprintf("root:%s@tcp(127.0.0.1:3306)/%s", os.Getenv("MYSQL_ROOT_PASSWORD"), os.Getenv("MYSQL_DATABASE"))


	conn, err := sqlx.Connect("mysql", dsn)
	if err != nil {
		log.Fatal(err)
		}
	DB = conn
	}