# Plan

This file is the **current state and the road ahead**. How we got here, with
the reasoning at each step, is in `docs/HISTORY.md`; the decisions in short
form are in `docs/DECISIONS.md`; commands are in `docs/COMMANDS.md`.

Last updated 2026-09-28.

# Scope

This is the full project, not a demo (decided 2026-09-25). What exists is
the foundation.

- Data model and DB schema: NOT a shortcut area. Schema changes go to the
  mentor first — they are expensive to undo.
- The shortcuts that remain are a debt list, under "Known shortcuts". Pay
  them down; do not add new ones silently.
- Tests are mine to write (decided 2026-09-25).
- The internship ends with a presentation to the mentor: every part has to
  be explainable.

# Where it stands

The mentor's 1–6 curriculum is done (Go + Gin, MySQL, sqlx, the wired
`POST`, the Laravel panel), and so is a demo shop that sends real events.

```
                      http://localhost  (Caddy, proxy/Caddyfile)
                     /          |                    \
          /market/...       /api/v1/...            /admin/...
         BLO demo shop ─POST─► Go + Gin API ◄─GET── Laravel panel
         (tracker.js)          │   (reads need       (panel_db)
                               │    the API key)
                               ▼
                      MySQL · events_db.events
```

- **Go API** (`backend/`): `POST /api/v1/events` — public, validated (UUID
  id, required columns, JSON-object payload, no future timestamps), 409 on
  a duplicate id, fills `user_ip` from the connection when the body has
  none. `GET /api/v1/events`, `/events/facets`, `/events/stats` — behind
  `EVENTS_API_KEY`. Binaries in `cmd/api`, `cmd/generate`, `cmd/send`.
- **Market** (`market/`): the "BLO" shop — listing, product, cart, demo
  login (customers 2001-2003). `tracker.js` sends `page_view`,
  `product_click`, `add_to_cart`, `checkout_start`; `session_id` rides in
  `event_payload` (30 idle minutes or a change of customer start a new one).
- **Panel** (`admin/`, under `/admin`): dashboard in Istanbul time (cards
  with hand-drawn SVG sparklines; events over time for 24 hours / 14 days
  against the period before, drawn with Chart.js; the shopping funnel;
  top products), events list with filters,
  staff accounts (manager / worker, screen-based access), own settings.
  Reads events only through the Go API; its own tables live in `panel_db`
  under a MySQL user with no rights on `events_db`.
- **MySQL** in Docker; a fresh volume gets `events` and the panel database
  from `db/schema.sql` and `db/panel-db.sh`.

# Roadmap

In order. Each item is its own branch, planned before it is built.
Improvising outside this list is fine — ask first.

## 1. Redis ← next

1. **Measure first — `cmd/loadgen`** ← start here. A small Go program:
   goroutines post N events per second for a set time (e.g. 500/s for 30 s)
   to `POST /api/v1/events`, time every request, and print sent / errors and
   latency p50 / p95 / p99. Run it against today's API (direct MySQL insert)
   and keep the numbers; run the same load after step 2. Without a baseline,
   "what did Redis speed up?" has no answer. Good Go concurrency practice.
   Its events must be easy to tell apart and delete: send them with
   `event_domain = "loadgen.test"` (and no `session_id`, so the funnel and
   top products ignore them), and document the `DELETE` in COMMANDS.md.
2. **Ingestion queue — Redis Streams + worker.** Design, as agreed
   2026-09-28:
   - **API** (`controllers/event.go`): validation and the `user_ip` fill stay
     where they are. **New:** if `event_timestamp` is missing the API sets it
     to now — once there is a queue, the database clock would record when the
     worker got to the event, not when it arrived. Then `XADD` the event (one
     JSON field) to stream `events` with `MAXLEN ~ 100000`, so Redis keeps a
     bounded tail instead of growing forever.
   - **Answer `202 Accepted`, not `201`**: stored later, not yet. A duplicate
     `event_id` can no longer be a `409` — the insert happens after the answer
     — so the worker treats it as already done (idempotent). The tracker and
     `cmd/send` count any 2xx as success, so neither changes; the Events log
     will show 202.
   - **Redis down → `503`** from the API. No fallback to a direct insert: two
     write paths are twice the ways to break.
   - **Worker** (`backend/cmd/worker`): `XREADGROUP` in group `ingest`, up to
     100 at a time, `BLOCK` 5 s when idle; `INSERT` each (the SQL moves here),
     then `XACK`. Duplicate key (1062) → ack. Any other error → no ack; the
     entry stays in the pending list. `XAUTOCLAIM` retries entries idle for
     30 s; after 5 deliveries an entry goes to `events:dead` and is acked, so
     one bad message cannot block the queue. Ctrl+C finishes the current batch
     before exiting (graceful shutdown).
   - **Compose**: a `redis` service, pinned image, `appendonly yes` and a
     volume, so a Redis restart keeps the queue. `REDIS_ADDR` in
     `backend/.env.example`. One Go dependency: `github.com/redis/go-redis/v9`
     (speaking Redis's protocol by hand is possible but not worth it).
   - Consequences to show the mentor: events appear in the panel once the
     worker has written them; with the worker stopped, the shop keeps getting
     202s, nothing shows up, and starting the worker drains the backlog.
   - Optional: a "queued" card on the dashboard (stream length + pending
     count) that shows when the worker falls behind.
   - The market and the panel do not change.
3. **Live counters in Redis.** `INCR` per hour, per day and per action,
   HyperLogLog for unique sessions and users. `/events/stats` reads counters
   instead of scanning the table; its JSON keeps the same shape, so the
   dashboard charts (built 2026-09-28 on SQL) do not change.
4. **Rate limiting** on `POST /api/v1/events` per IP, in Redis. The open
   endpoint (mentor question 10) gets a ceiling.

## 2. Panel analysis — now that the data is real

5. **Live event feed**: events appear in the panel as they are clicked in
   the shop (polling first; server-sent events if it earns its place).
6. ~~**Funnel**: listing view → product click → add to cart → checkout, per
   session, with conversion between steps.~~ Done 2026-09-28, on the
   dashboard.
7. **Session view**: one `session_id` as a timeline — anonymous browsing,
   the login, the cart.
8. ~~**Top products** by clicks and add-to-carts.~~ Done 2026-09-28, on the
   dashboard in place of "Events by action".

## 3. Infrastructure — pay the debt

9. **Schema migrations on the Go side** (numbered SQL files and a way to
   apply them). Needed before any schema answer from the mentor can land:
   `session_id` column, indexes, `received_at`.
10. **A MySQL user for Go** with `SELECT, INSERT` on `events_db.events` only;
    Go still connects as `root`.
11. **Go API and worker in Docker**, so one `docker compose up` starts
    everything but Laravel's dev tools.
12. **CI** (GitHub Actions): `go vet`, `gofmt`, PHP lint on every push; my
    tests join it when they exist.

Also still owed: the "why Gin" and "why sqlx" write-ups in `DECISIONS.md`,
and every "What I tried / What I'd do differently" there — mine to write.

# How to run everything

```
docker compose up -d                 # MySQL + the Caddy proxy on port 80
cd backend && go run ./cmd/api       # :8080 — the shop posts here, the panel reads here
cd admin && php artisan migrate      # once — panel tables in panel_db
cd admin && php artisan db:seed      # once, on an empty panel_db — manager + worker
cd admin && npm run dev              # Vite, leave running
cd admin && php artisan serve        # :8000 — reached through the proxy
```

Then everything is on **http://localhost**: the shop at `/market/list`, the
panel at `/admin`, the API at `/api/v1`.

Keys that must match across `.env` files: `EVENTS_API_KEY` (backend, admin)
and `PANEL_DB_PASSWORD` (root) = `DB_PASSWORD` (admin). Each directory has a
`.env.example`.

Panel logins: `manager@example.com` / `password` (sees Users) and
`worker@example.com` / `password` (403 on `/admin/users`).

# I can explain this

- Request flow: JSON body → `gin.Context` → `ShouldBindJSON` → `Event`
  struct → `Exec` args → `?` placeholders → MySQL row.
- Side-effect imports (`_ "...mysql"`, `_ "...godotenv/autoload"`): imported
  only so their `init()` runs (driver registration / `.env` load).
- `godotenv/autoload` reads `.env` from the process working directory, so
  the app needs `backend/.env` when run via `cd backend && go run ./cmd/api`.
- Docker named volume `mysql_data` persists across `up`/`down`; `down -v`
  wipes it. That's why an old `events` table survived a schema change.
- `*int` / `*string` struct fields = nullable columns (`nil` → `NULL`).

# Open questions for mentor

**Decided without the mentor on 2026-09-28** — ordering and reversible
choices, not schema. Tell the mentor; change course if they disagree:

- **Market before Redis.** Redis is easier to see working under real clicks
  than under a replayed synthetic file, and the market does not change when
  Redis lands.
- **Redis is a queue** between `POST` and MySQL, not a cache.
- **Streams, not Lists.** A List loses the event a worker had popped when it
  crashes; a Stream keeps it until the worker acks. Streams' consumer groups
  are also the concept Kafka is built on.
- **`session_id` goes in `event_payload` for now**, not a column.

Still for the mentor — schema changes are expensive to undo:

4. **`session_id` as a column?** It lives in the payload for now. A column
   makes funnels a plain `GROUP BY session_id` and can be indexed.
5. **"Specific access":** screen-based (what a worker can do — built that
   way) or row-level (a worker sees only some `event_domain` values)?
   Row-level is a join table plus one `where`, on top of what exists.
6. Separate `user` / id table, or keep `user_id` on the flat `events` row?
   (Raw notes: "tabloları ayırıcam, id tablosu".) `user_id` is nullable for
   anonymous events — OK?
7. Store when an event **reached us** (`received_at`) as well as when it
   happened (`event_timestamp`, client-supplied)? Makes late delivery
   visible.
8. Indexes on `events` for the filtered columns and `event_timestamp`?
9. `event_action` kept as its own column, not a payload key. Agree?
10. `POST /api/v1/events` is open to anyone. Per-domain API key, rate limit
    (roadmap 4), or something else, once a real tracker sends from the
    internet?
11. Times are stored in UTC. Since 2026-09-28 the dashboard counts and shows
    them in Istanbul time (`DISPLAY_TIMEZONE`); the events list still shows
    UTC. Istanbul everywhere?

# Known shortcuts

## Security

- **Go connects to MySQL as `root`** and could drop any table (roadmap 10).
- `POST /api/v1/events` is open to anyone who can reach the address, with
  no rate limit (roadmap 4, mentor question 10).
- `EVENTS_API_KEY` is one shared secret: no rotation, no per-client keys.
  Fine with one client (the panel); not once there are several.
- Seeded panel accounts use the password `password`, written in
  `DatabaseSeeder.php` and in "How to run everything" above — and **the repo
  is public**. Harmless while the panel only answers on this machine. Before
  it is deployed anywhere: read it from an env var with no default,
  `env('SEED_MANAGER_PASSWORD') ?? throw new RuntimeException(...)`.
- Proxy trust is broad locally. Laravel trusts `X-Forwarded-*` from **any**
  address (`trustProxies(at: '*')`); Go trusts it from `127.0.0.1` / `::1`
  (`TRUSTED_PROXIES`), where Docker Desktop delivers Caddy's requests — so
  any program on this machine can claim an address. The login throttle
  (`throttle:5,1` on `POST /admin/login`) keys on that forwarded address.
  A real deployment names the proxy's address instead.
- Login timing leaks whether an email has an account: `Auth::attempt` only
  runs bcrypt when the user exists (measured 2026-09-22: ~80 ms slower for
  a real account). The generic error message makes enumeration harder, not
  impossible; the throttle caps its speed. Fix if it ever matters: hash a
  dummy password when no user is found.
- No password reset, no email verification, no "remember me". `/settings`
  changes a password you still know; a forgotten one needs a manager on
  `/admin/users`, or the seeder for a manager.
- Changing your password on `/settings` does not sign out your other
  sessions (`logoutOtherDevices()` needs the `AuthenticateSession`
  middleware, which is not enabled).
- A deleted panel account is gone, not deactivated — no soft deletes.
- Nothing constrains `role` at the database level. `User::ROLES` and the
  FormRequests reject anything but `manager`/`worker`, but a hand-written
  `UPDATE users SET role='owner'` would stick and `isManager()` would answer
  false for it.
- **The proxy answers the whole network.** Compose publishes `"80:80"`, so
  anyone on the same network (an office one included) reaches `/admin` —
  with the public seed password — and `POST /api/v1/events`. Fix when it
  matters: `"127.0.0.1:80:80"`, and open it up only while testing from
  another device.

## Data

- `user_ip` is stored **in full**. An IP address is personal data under
  KVKK; a real system would mask it (e.g. drop the last block) or state a
  reason and retention period.
- `user_ip`: a body value wins, otherwise the server takes `c.ClientIP()`
  (since 2026-09-28; before that it was "body only"). A body value is
  whatever the client claims. Locally every visitor is this machine, so
  every market event carries the same address.
- The market's login is a **demo login**: three fixed customers, no
  password, remembered in the browser. Nothing verifies who is "logged in".
- The tracker sends the **browser's clock** as `event_timestamp`. Go refuses
  anything more than a minute ahead, but a clock running behind is stored
  as is, and any past timestamp is accepted, however old.
- `event_id` comes from the client; the server never makes one. It is
  checked to be a UUID and a resend is a 409.
- `session_id` lives inside `event_payload`: reading it means JSON
  extraction, which no index helps (mentor question 4).
- Market events carry `event_domain = localhost` locally — the tracker sends
  the page's hostname, which is right in production and odd on a laptop.
- Products live in `market/products.json`; nothing ties `product_id` in an
  event to a products table, because there is none. Since 2026-09-28 the
  tracker sends `product_name`, `brand` and `category` with each product
  event so the panel can name products; earlier events have only the id,
  and a name changed in the JSON shows up only in new events.
- Two clocks in one panel: the dashboard counts and shows Istanbul time,
  the events list shows UTC (mentor question 11).
- The funnel counts a step if the visit ever did it, not in order: a visit
  that added to cart and later viewed the listing still counts as having
  gone listing → cart. It only sees market visits (events with a
  `session_id`).
- Every dashboard load runs the stats queries as full scans — totals, the
  24 hourly and 14 daily buckets, the funnel's per-session grouping over
  JSON. Fine at hundreds of rows; roadmap 3 replaces them with counters.
- The tracker only works on `localhost` or HTTPS. It makes ids with
  `crypto.randomUUID()`, which browsers offer only in a secure context;
  opened from a phone as `http://<mac-ip>/market/list` it throws and sends
  nothing (checked 2026-09-28). Fix: a fallback built on
  `crypto.getRandomValues()`. Until then, test phones and tablets with
  Chrome's Device Mode on `localhost` — an iPad-sized touch screen sends
  `event_platform = tablet`.

## Operations

- `schema.sql` runs automatically only on an **empty** volume. There is no
  way yet to apply a schema *change* to an existing database (roadmap 9).
- No index on any filtered column: every filter and every `DISTINCT` is a
  full table scan (mentor question 8). Every `/admin/events` load calls
  `/events/facets`, which runs four `SELECT DISTINCT` queries.
- The filter rules (column widths, `per_page` 25/50/100, `Y-m-d` dates) are
  written twice — in the panel's form validation and in Go — and must be
  changed together.
- Three `.env` files (root, `backend/`, `admin/`) share values kept in sync
  by hand; nothing checks they agree.
- The DSN host is hardcoded to `127.0.0.1:3306` in `backend/config/db.go`.
- Gin's validation errors go back to the client as-is (`Key:
  'Event.EventID' Error:Field validation for ...`); a polished API would
  name the JSON fields.
- Switching to a branch without `market/` or `proxy/` and back leaves the
  proxy container bound to deleted folders — every `/market` URL 404s until
  `docker compose up -d --force-recreate proxy` (in COMMANDS.md).

---

# Raw mentor notes (verbatim — do not lose)

```
cumaya kadar veri seti oluştur
verilerde ne olucak bakılıcak
event id, unique id
serviste alıcağın datalar yazılıcak, tabloları ayırıcam
ayrı tablolar yapılabilir id tablosu yapılıp diğer tabloyu bağlayabilirim id tablosunu
sentetik veri üretilicek
oluşturduğun veri setinde göre istek oluştur
go gin kaldır son sürüm çalış
mysql insert edilicek
go da Marshall methodlar kullanılacak
table plus
DbGate -> DB ide için verileri görüp sorgulamak için
DBEngin -> mysql server kaldırmak için

emre abi izindeyken:
panelle uğraş

1.  Ben bir event manager sistemi kurucam verilerim ne olmalı, araştırılcak, tipleri ne olmalı
    Event id ui id olmalı
    Bunlar database deki bir tablonun structure ı olucak, sonuçta o verilere göre istek atılıcak. Sonra insert edilicek database e
    event projesinde tutabileceğimiz dataları da araştırıp bir structure oluşturalım. Hangileri anlamlı hangileri değil neler tutmamız lazım bir kafa yorarsın.

event_platform olsun kesin -> app, web, tablet vs gibi
event_domain -> veri topladığın site gibi
event_source olsun -> listelemei detay, sepet vs gibi
event_id -> unique olucak kesinlikle. bunu uuid şeklinde düşün. 2)
Go (Gin) - Niye Gin ? araştır
Son sürüm go ile gini kullanarak bir servis oluştur. Post at get at parametre çek print et, kurcala. Docker ile kaldır (karışıksa en başta yapma localden ilerle) 3)
MySQL kurulumu, tablo oluşturma, dummy veriler. user select falan bak 4)
Ben go da MySQL bağlantısını hangi kütüphane ile kurayım? araştır 5)
Bunları birleştir. Projeden bağımsız. Full örnek. Bi tane tablo olucak, customer mesela, id alanı olucak (auto implement) bir de name surname olucak(varchar). Postmanden bir customerın isim soyisim atılıcak (POST ile) go gin ile yakala sonra tabloma insert edicem. 6)
php panel (notlarda var)

ZAMAN KALIRSA
Update delete serviceleri yazabiliriz
update - customer id alıcak, kullanıcının ismini ve soy ismini alıcak
delete - customer id alsın onu silsin
get - customer id at ismini ve soyismini döndürür
post ta olur
bunlara 4 ayrı servisten yazılıcak
api/v1/customer/get
api/v1/customer/update
api/v1/customer/remove
api/v1/customer/create
http method post



debug delve araştır
uvicorn
hotreload
```
