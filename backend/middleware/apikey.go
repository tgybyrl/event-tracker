package middleware

import (
	"crypto/subtle"
	"strings"

	"github.com/gin-gonic/gin"
)

// RequireAPIKey lets a request through only if it carries
// "Authorization: Bearer <key>". Everything else is stopped with a 401 before
// the handler runs.
//
// The read endpoints hand out every stored event, so they must not be open
// the way POST /api/v1/events is. The panel is the one client that knows the
// key.
func RequireAPIKey(key string) gin.HandlerFunc {
	return func(c *gin.Context) {
		sent, ok := strings.CutPrefix(c.GetHeader("Authorization"), "Bearer ")

		// ConstantTimeCompare takes the same time whether the first byte or
		// the last byte differs. A plain == stops at the first wrong byte,
		// and that timing difference can be measured to guess the key one
		// byte at a time.
		if !ok || subtle.ConstantTimeCompare([]byte(sent), []byte(key)) != 1 {
			c.AbortWithStatusJSON(401, gin.H{"error": "missing or invalid API key"})
			return
		}

		c.Next()
	}
}
