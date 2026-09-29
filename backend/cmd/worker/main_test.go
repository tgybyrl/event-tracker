package main

import (
	"database/sql/driver"
	"errors"
	"fmt"
	"testing"

	"github.com/go-sql-driver/mysql"
)

// decide is where the worker chooses between acknowledging an entry,
// retrying it later and waiting for MySQL. Each row is an error an INSERT
// can return and the choice it must lead to.
func TestDecide(t *testing.T) {
	tests := []struct {
		name string
		err  error
		want outcome
	}{
		{"insert worked", nil, stored},

		// The row is already there: acknowledge, do not retry forever.
		{"duplicate key", &mysql.MySQLError{Number: 1062}, stored},
		// errors.As looks through wrapping, so a duplicate reported inside
		// another error is still recognised.
		{"duplicate key, wrapped", fmt.Errorf("insert: %w", &mysql.MySQLError{Number: 1062}), stored},

		// MySQL answered and refused. Retried; the delivery count decides
		// when to give up.
		{"value too long", &mysql.MySQLError{Number: 1406}, retryLater},
		{"deadlock", &mysql.MySQLError{Number: 1213}, retryLater},

		// No answer from MySQL at all. Not the entry's fault: wait, and do
		// not spend its deliveries.
		{"connection refused", errors.New("dial tcp 127.0.0.1:3306: connect: connection refused"), mysqlDown},
		{"broken connection", driver.ErrBadConn, mysqlDown},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := decide(tt.err); got != tt.want {
				t.Errorf("decide(%v) = %s, want %s", tt.err, names[got], names[tt.want])
			}
		})
	}
}

// Readable names for failure messages; outcome itself is just a number.
var names = map[outcome]string{
	stored:     "stored",
	retryLater: "retryLater",
	giveUp:     "giveUp",
	mysqlDown:  "mysqlDown",
}
