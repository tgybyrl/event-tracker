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

// Upper limit on this process's MySQL connections, idle or in use.
const maxDBConns = 25

func ConnectDB() {
	// parseTime=true makes the driver return DATETIME/TIMESTAMP columns as
	// time.Time. Without it they come back as raw bytes and scanning
	// event_timestamp into a *time.Time fails.
	dsn := fmt.Sprintf("root:%s@tcp(127.0.0.1:3306)/%s?parseTime=true", os.Getenv("MYSQL_ROOT_PASSWORD"), os.Getenv("MYSQL_DATABASE"))

	conn, err := sqlx.Connect("mysql", dsn)
	if err != nil {
		log.Fatal(err)
	}

	// conn is a pool of connections, not one. By default Go keeps only 2
	// idle ones: under load every extra connection was opened for one query
	// and closed right after (857 in 30 s at 1000 events/s, see PLAN.md),
	// and opening one costs far more than the INSERT it is for. Keeping as
	// many idle as may be open means a connection, once opened, is reused.
	// The cap keeps this process well under MySQL's max_connections (151),
	// which the panel and the mysql client share; past it, a query waits
	// for a free connection instead of opening another.
	conn.SetMaxOpenConns(maxDBConns)
	conn.SetMaxIdleConns(maxDBConns)

	DB = conn
}
