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
  `actionPayloads` map in `cmd/generate`. `actions` and
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
  *(Since 2026-09-25 there are three — root, `backend/`, `admin/` — each
  with a committed `.env.example` naming its keys. The values that must
  match across files are listed in PLAN.md's "How to run everything".)*
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

## 2026-09-22 — Phase D: hand-rolled auth instead of Breeze

Problem: the panel needed login and two roles. Laravel ships a starter kit
  (Breeze) that generates exactly this in one command.
What I tried: TODO
What I chose and why: wrote it by hand — an `AuthController` with
  `create`/`store`/`destroy`, the built-in `auth`/`guest` middleware, and one
  `Gate::define('manage-users', ...)` in `AppServiceProvider`. Breeze was
  rejected because it generates its own Blade layouts and Tailwind config,
  which would have fought the phase A design; I would have spent longer
  deleting its views than writing three controller methods. Nothing here is
  custom auth — `Auth::attempt`, the session guard and the Gate are all
  Laravel's own; only the views are mine.
  Four smaller calls inside it, each worth being able to defend:
  (1) `session()->regenerate()` after a successful attempt — session
  fixation. An id planted before login would otherwise still be valid after.
  (2) One generic failure message for "no such email" and "wrong password".
  Distinguishing them confirms which addresses have accounts.
  (3) Logout is `POST` with a CSRF token, not the `GET` link phase A left
  behind: a `GET /logout` can be triggered by an `<img src>` on any page.
  (4) `throttle:5,1` on `POST /login` — one middleware string; without it the
  form is an unlimited password oracle.
What I'd do differently: TODO

## 2026-09-22 — `role` is a plain string column, not a PHP enum

Problem: `users.role` holds `manager` or `worker`. Laravel can cast a column
  to a backed enum, which would stop typos at the boundary.
What I tried: TODO
What I chose and why: plain `VARCHAR(20)`, defaulting to `'worker'`, with a
  single `User::isManager()` helper. Two values did not justify introducing
  a new concept to a codebase that has not needed one yet, and the one place
  the literal `'manager'` appears is that helper — the Gate, the sidebar and
  phase E all go through it. The default is `worker` rather than `manager`
  so that forgetting to set a role grants the *least* access.
  The gap this leaves: nothing rejects `role = 'banana'` today. Acceptable
  only because the seeder is currently the one thing that writes the column;
  phase E's `FormRequest` adds `in:manager,worker`. If a third role ever
  appears, that is the moment the enum earns its place.
What I'd do differently: TODO

## 2026-09-23 — Go binaries under `cmd/`, no `pkg/`

Problem: `backend/` held three `package main` directories in three shapes
  (the API at the root, two tools under `tools/`), and GitHub examples all
  seemed to use `pkg/`.
What I tried: TODO
What I chose and why: `cmd/api`, `cmd/generate`, `cmd/send` — Go's own
  module-layout guide for a module with several commands. Each `package
  main` directory is its own binary, so three `main.go` files were never
  wrong, only inconsistent. No `pkg/`: it means "importable by other
  projects", and nothing imports this module; it is also not an official
  convention. No `internal/` either, for the same reason.
What I'd do differently: TODO

## 2026-09-23 — `event_timestamp` is the client's time

Problem: the column recorded when the row was *inserted*, not when the
  event *happened*. The INSERT left it out and the DB default filled it.
What I tried: TODO
What I chose and why: accept the client's timestamp. The Go field is
  `*time.Time` — a plain `time.Time` cannot say "not sent", its zero value
  is year 0001 — and the INSERT uses `COALESCE(?, CURRENT_TIMESTAMP)`, so a
  body without one still gets the database clock. Real trackers receive
  events late (offline queues, batched mobile sends). Timestamps more than
  a minute in the future are refused.
What I'd do differently: TODO

## 2026-09-25 — The panel reads events through the Go API

Problem: two programs read the same table. Every coming change to
  `events` (`session_id`, indexes, `received_at`) could break the panel,
  and the panel connected as `root`, able to write to Go's table.
What I tried: TODO
What I chose and why: Go serves `GET /api/v1/events`, `/facets` and
  `/stats`; the panel calls them through one class, `EventsApi`, and its
  `Event` model is deleted. The panel now depends on a JSON shape, not on
  the table, so Go can change the table freely. The reads sit behind an API
  key (constant-time compare, server refuses to start without one); `POST`
  stays public because browsers must be able to send events.
  Cost: the filter rules exist twice (panel form + Go), and the panel shows
  a 503 page when Go is down.
What I'd do differently: TODO

## 2026-09-25 — The panel gets its own database and MySQL user

Problem: Laravel's tables sat next to `events` in `events_db`, and the
  panel connected as `root`.
What I tried: TODO
What I chose and why: `panel_db` plus a `panel` user with rights on
  `panel_db` only — `SELECT` on `events_db.events` is denied. Created by
  `db/panel-db.sh`, which docker compose runs on a fresh volume together
  with `schema.sql`. The three existing accounts were copied across with
  their password hashes, so nobody had to reset a password.
What I'd do differently: TODO

## 2026-09-28 — One address behind Caddy

Problem: the shop, the API and the panel ran on three ports. A shop page
  on one port posting to an API on another is cross-origin, so the browser
  would need CORS headers from Go.
What I tried: TODO
What I chose and why: a reverse proxy on port 80 routes by path: `/market`
  (static files), `/api` (Go), `/admin` (Laravel, now under that prefix).
  Same origin for shop and API, so no CORS code at all. Caddy over nginx
  because its config is short enough to read in one sitting; all routing
  lives in `proxy/Caddyfile`, so switching later touches one file.
What I'd do differently: TODO

## 2026-09-28 — Market before Redis; Redis Streams over Lists

Problem: the build order says Redis next, but every event was synthetic —
  a queue under a replayed file shows that it runs, not what it is for.
What I tried: TODO
What I chose and why: build the demo market first, so Redis lands under
  real clicks; the market does not change when Redis arrives (same URL,
  same JSON). For Redis itself, **Streams**: a List loses the event a
  worker had popped if it crashes before the INSERT, a Stream keeps it until
  the worker acknowledges it. Consumer groups are also the idea Kafka is
  built on, which is the later step in the build order.
What I'd do differently: TODO

## 2026-09-28 — `session_id` inside the payload; a new session per person

Problem: single events cannot answer "what did this visitor do in one
  visit" — funnels need something tying events together.
What I tried: TODO
What I chose and why: the tracker keeps a `session_id` in localStorage and
  sends it inside `event_payload`, not as a column: a column is a schema
  change, and those wait for the mentor. A session ends after 30 idle
  minutes, or when the person changes (logout, or another customer logs
  in). Logging in from anonymous keeps the session, so what someone did
  before logging in stays attached to them (identity stitching).
  Cost: querying it means reading JSON (`event_payload->'$.session_id'`),
  which no index helps.
What I'd do differently: TODO

## 2026-09-28 — `user_ip`: the body wins, otherwise the connection

Problem: the 2026-09-22 rule was "body only". Then real browsers started
  sending events, and a browser cannot know its own public address, so every
  market event had `user_ip` NULL.
What I tried: TODO
What I chose and why: keep a body value when there is one (server-to-server
  senders post someone else's address), otherwise `c.ClientIP()`. Behind
  Caddy that has to come from `X-Forwarded-For`, and only the proxy may set
  it: `SetTrustedProxies` names it (`TRUSTED_PROXIES`, default `127.0.0.1`,
  where Docker Desktop delivers the proxy's requests).
  Open cost: the IP is personal data under KVKK and is stored in full.
What I'd do differently: TODO

## 2026-09-28 — Dashboard charts: SVG by hand, then Chart.js for the time chart; Istanbul time

Problem: the dashboard was four numbers and a list. With real traffic it
  could show trends and where shoppers drop off.
What I tried: TODO
What I chose and why: three charts - events over time (24 hours / 14
  days), a shopping funnel, sparklines in the cards - drawn as SVG in
  Blade components, not with Chart.js: no new dependency, and they use the
  panel's own colours and font. Chart.js stays the fallback if they are
  not good enough.
  Same day, the fallback was taken for the time chart only: as a column
  chart it looked flat, and what came next - a second line for the period
  before, smooth curves, a crosshair tooltip, an entry animation - is what
  a chart library is for. `chart.js` 4.5.1, pinned, only the line-chart
  parts imported, loaded only on the dashboard. The sparklines and the
  funnel stay hand-drawn: simple, and they fit.
  The funnel counts visits (`session_id`) that reached each step, where a
  step only counts if every earlier one happened too: viewed the listing
  -> clicked a product -> added to cart -> started checkout. It ignores
  the order of the steps.
  Buckets and "today" use the display clock (`DISPLAY_TIMEZONE`, default
  Europe/Istanbul). Storage stays UTC; only the grouping for people to
  read changes. Before this, "today" started at 03:00 Istanbul time.
What I'd do differently: TODO

## 2026-09-30 — MySQL pool: 25 connections, kept open

Problem: `config/db.go` set no pool limits. Under load Go opened a MySQL
  connection per overlapping request and closed it after one query (412
  in 30 s at 1000/s), with no upper bound below MySQL's 151.
What I tried: TODO
What I chose and why: `SetMaxOpenConns(25)` and `SetMaxIdleConns(25)`: a
  connection, once opened, is reused; a burst waits for a free one instead
  of opening more. Measured: 412 → 25 new connections, latency unchanged.
  The first guess — that the churn caused the p99 of 29 ms — was wrong; the
  API's log lines going to a terminal did. Kept for the bound, not speed.
What I'd do differently: TODO

## 2026-09-30 — The ingestion queue: 202, ACKED trimming, a worker that waits for MySQL

Problem: `POST /api/v1/events` inserted into MySQL before answering, so a
  slow or stopped MySQL meant slow or failed event sends.
What I tried: TODO
What I chose and why: the API adds the event to the Redis stream `events`
  and answers **202 Accepted**; `cmd/worker` inserts it later. Details
  decided while building:
  - `MAXLEN ~ 100000` with **`ACKED`** (Redis 8.2+): trim only what the
    worker has acknowledged. Plain `MAXLEN` would drop unwritten events once
    a backlog passed the cap. Cost: unbounded growth while no worker runs.
  - The worker **pings MySQL before every read and takes nothing while it
    is down**. Without that, the "5 deliveries → `events:dead`" rule, meant
    for bad messages, would bury good events during an outage.
  - `XPENDING` + `XCLAIM` rather than `XAUTOCLAIM`, for the delivery count.
  - Duplicates: `409` is gone; the worker acks a duplicate key as done.
  - `BLOCK 1s`: go-redis ignores a cancelled context during a blocking
    read, so Ctrl+C on an idle worker waited out the block.
  - **The worker is its own program (`cmd/worker`), not a goroutine inside
    the API.** A consumer goroutine started in `cmd/api/main.go` would also
    have worked and is one program less to run. Separate, because:
    writers scale on their own (one worker ~640/s fell behind at 1000/s, a
    second `go run ./cmd/worker` kept up — the API needed no second copy at
    p99 1.6 ms); a panic in the worker does not take down the endpoint that
    accepts events; the worker can be stopped and started without touching
    the shop (the "worker stopped, shop still gets 202, backlog drains on
    start" demo depends on it); its MySQL connections and CPU are not
    shared with request handling; and producer / consumer as separate
    programs is the shape Kafka keeps. Price: one more program to start —
    forgotten, the shop gets 202s and the panel shows nothing — and two
    programs that must agree on names (`config/redis.go`, `models.Event`).
  Measured: p99 at 1000/s 5.4 → 1.6 ms; one worker writes ~640/s, two keep
  up with 1000/s.
What I'd do differently: TODO
