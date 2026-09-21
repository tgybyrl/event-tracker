# Decisions

Template:

## [date] — <what>

Problem:
What I tried:
What I chose and why:
What I'd do differently:

---

Entries below are pre-filled with the facts. Fill in "What I tried" and
"What I'd do differently" in your own words — the mentor will ask.

## 2026-09-04 — MySQL access library for Go

Problem: mentor asked which library to use for the Go↔MySQL connection.
What I tried: TODO
What I chose and why: `jmoiron/sqlx` on top of the stdlib `database/sql`,
  with `go-sql-driver/mysql` as the driver. `sqlx` adds struct scanning and
  named params over `database/sql` without being a full ORM. TODO: your own
  reasoning — why not raw `database/sql`, why not an ORM like GORM.
What I'd do differently: TODO

## 2026-09-04 — Web framework: Gin

Problem: mentor asked "why Gin?" — needs a real answer, not "the tutorial
  used it".
What I tried: TODO
What I chose and why: Gin. TODO: research + your reasoning (routing,
  middleware, `c.ShouldBindJSON`, popularity vs alternatives like Echo,
  Chi, or net/http alone).
What I'd do differently: TODO

## 2026-09-04 — Dropped `event_location` column; geo goes in payload

Problem: `event_location VARCHAR(50)` was in the schema with no clear
  meaning (URL? country? screen?). Undefined field in the "get it right"
  area.
What I tried: TODO
What I chose and why: removed the column. Location is optional and rare
  (only when the user grants permission), so it lives inside `event_payload`
  as `{"location": {"lat": ..., "lon": ...}}` instead of a mostly-NULL
  column. Kept `user_ip VARCHAR(45)` as a real column for coarse origin.
What I'd do differently: TODO

## 2026-09-04 — `user_id` is nullable

Problem: events can come from users who aren't logged in.
What I tried: TODO
What I chose and why: `user_id INT` nullable in the schema, `*int` in the
  Go struct (`nil` → `NULL`, not `0`). Anonymous events are still tracked
  by `user_ip`. Open with mentor: whether a separate linked id table is
  wanted instead.
What I'd do differently: TODO

## 2026-09-15 — `payloadFor` panics on an unmapped action

Problem: `event_payload` now varies per `event_action` via an
  `actionPayloads` map in `tools/generate`. `actions` and
  `actionPayloads` are two separate lists that must stay in sync — if a
  new action is added to `actions` without a matching entry in
  `actionPayloads`, the lookup misses.
What I tried: TODO
What I chose and why: `payloadFor` panics immediately if the map lookup
  misses (`ok == false`), instead of falling back to a default payload
  or silently skipping the event. This is generator tooling I run
  myself, not a live service — a missing entry means I forgot to update
  the map, and I want that caught the moment I run it, not hidden in
  output I might not inspect closely.
What I'd do differently: TODO

## 2026-09-04 — `.env` kept in two places

Problem: `docker compose` reads `.env` from the repo root; the Go app's
  `godotenv/autoload` reads `.env` from its working directory (`backend/`).
  One file can't serve both.
What I tried: TODO (single root file + run app from root; symlink; export
  vars)
What I chose and why: two copies — `./.env` for compose, `backend/.env`
  for the app — kept in sync by hand. Simplest to explain; no symlink
  concept, no code change.
What I'd do differently: TODO

## 2026-09-21 — Panel access model: screen-based, and phases D/E swapped

Problem: the panel is for a company's staff, not just me. There is a manager
  account that grants workers either general access or access "specific" to
  something. PLAN.md's phases D and E were written before this was known —
  D was plain CRUD on `users`, E was a single `Auth::attempt()` login, and
  neither had any notion of a role.
What I tried: TODO
What I chose and why: two things.
  (1) Access is **screen-based**, not row-based: a role controls what you can
  do, not which events you can see. So `users` gets a `role` column
  (`manager` / `worker`) and nothing else — no join table, no per-row
  scoping, no change to `events`. The alternative was row-level access tied
  to `event_domain`. Rejected for now because it is strictly more work and
  nothing has asked for it; it is also additive later (a join table plus one
  `where`), so choosing wrong here is cheap. Provisional until the mentor
  confirms — logged under "Open questions for mentor".
  (2) Login moves ahead of user management (old E becomes D). Phase E's user
  CRUD is itself a screen only a manager may open, so the Gate that protects
  it needs auth to already exist. Built the other way round, the screen gets
  written open and gated afterwards — the same work twice.
  Authorization goes through Laravel's built-in `Gate`/`Policy`, not
  `spatie/laravel-permission`. Two roles do not justify a dependency, and
  the mentor's note says "full laravel yapıları kullanılıcak".
  The first manager comes from a seeder: you cannot create the first manager
  through a screen that only a manager may open.
What I'd do differently: TODO
