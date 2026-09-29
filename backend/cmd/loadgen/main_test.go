package main

import (
	"testing"
	"time"
)

// The simplest shape: one input, one call, one comparison.
func TestPercentile(t *testing.T) {
	sorted := []time.Duration{1 * time.Millisecond, 2 * time.Millisecond}

	// Two values, p50: rank = ceil(0.50 × 2) = 1, so the 1st smallest.
	got := percentile(sorted, 0.50)
	want := 1 * time.Millisecond
	if got != want {
		t.Errorf("p50 = %s, want %s", got, want)
	}

	// p99: rank = ceil(0.99 × 2) = ceil(1.98) = 2, so the 2nd smallest.
	got = percentile(sorted, 0.99)
	want = 2 * time.Millisecond
	if got != want {
		t.Errorf("p99 = %s, want %s", got, want)
	}
}

// The same check for many cases: each row is one input and its answer, and
// the loop runs every row as its own subtest.
func TestPercentileTable(t *testing.T) {
	tests := []struct {
		name   string
		sorted []time.Duration
		p      float64
		want   time.Duration
	}{
		// rank = ceil(0.50 × 10) = 5 → the 5th smallest
		{"p50 of 1..10ms", ms(1, 2, 3, 4, 5, 6, 7, 8, 9, 10), 0.50, 5 * time.Millisecond},
		// rank = ceil(0.95 × 10) = ceil(9.5) = 10 → the largest
		{"p95 of 1..10ms", ms(1, 2, 3, 4, 5, 6, 7, 8, 9, 10), 0.95, 10 * time.Millisecond},
		// rank = ceil(0.99 × 100) = 99 → the 99th, one below the largest
		{"p99 of 1..100ms", upTo(100), 0.99, 99 * time.Millisecond},
		// p = 1 is the largest value, i.e. max
		{"p100 of 1..10ms", ms(1, 2, 3, 4, 5, 6, 7, 8, 9, 10), 1.0, 10 * time.Millisecond},

		// Edge cases a real run never produces, so only a test checks them.
		// No results at all: nothing to index, the function says 0.
		{"empty", nil, 0.50, 0},
		// One value is every percentile.
		{"one value, p50", ms(7), 0.50, 7 * time.Millisecond},
		{"one value, p99", ms(7), 0.99, 7 * time.Millisecond},
		// rank = ceil(0 × 10) = 0, and sorted[0-1] does not exist. The
		// `rank < 1` guard turns it into the smallest value.
		{"p0 of 1..10ms", ms(1, 2, 3, 4, 5, 6, 7, 8, 9, 10), 0, 1 * time.Millisecond},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := percentile(tt.sorted, tt.p)
			if got != tt.want {
				t.Errorf("got %s, want %s", got, tt.want)
			}
		})
	}
}

// ms turns plain numbers into milliseconds, so a row reads ms(1, 2, 3)
// instead of repeating time.Millisecond for every value.
func ms(values ...int) []time.Duration {
	out := make([]time.Duration, len(values))
	for i, v := range values {
		out[i] = time.Duration(v) * time.Millisecond
	}
	return out
}

// upTo returns 1ms, 2ms, … n ms, already sorted.
func upTo(n int) []time.Duration {
	out := make([]time.Duration, n)
	for i := range out {
		out[i] = time.Duration(i+1) * time.Millisecond
	}
	return out
}
