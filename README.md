# Event tracker

An event tracking system built as an internship project at FLO Group: a
demo shop sends what visitors do as events, a Go service stores them in
MySQL, and a Laravel admin panel lets staff browse, filter and summarise
them.

```
                      http://localhost  (Caddy reverse proxy)
                     /          |                    \
          /market/...       /api/v1/...            /admin/...
         demo shop ──POST──► Go + Gin API ◄──GET──  Laravel panel
       (HTML/CSS/JS,           │   (key-protected    (panel_db: staff
        tracker.js)            │    reads)            accounts, sessions)
                               ▼
                      MySQL · events_db.events
```

- **`market/`** — the demo shop "BLO": listing, product detail, cart and a
  demo login. `market/js/tracker.js` turns page views, product clicks, add
  to cart and checkout into events.
- **`backend/`** — Go + Gin. `POST /api/v1/events` accepts events (public),
  `GET /api/v1/events`, `/events/facets` and `/events/stats` serve the panel
  (behind an API key). `cmd/generate` and `cmd/send` make and replay a
  synthetic dataset.
- **`admin/`** — Laravel panel under `/admin`: dashboard, events list with
  filters, staff accounts with manager/worker roles, own-account settings.
  It reads events only through the Go API.
- **`proxy/`** — the Caddyfile that puts all three behind one address.
- **`db/`** — `schema.sql` (the `events` table) and `panel-db.sh` (the
  panel's database and MySQL user); both run on a fresh MySQL volume.

## Run it

Needs Docker, Go, PHP 8.5 + Composer, Node.

```
cp .env.example .env                 # then fill in the passwords
cp backend/.env.example backend/.env # EVENTS_API_KEY: openssl rand -hex 32
cp admin/.env.example admin/.env     # same key, panel DB password; then php artisan key:generate

docker compose up -d                 # MySQL + Caddy on port 80
cd backend && go run ./cmd/api       # Go API on :8080
cd admin && composer install && npm install
cd admin && php artisan migrate && php artisan db:seed
cd admin && npm run dev              # leave running
cd admin && php artisan serve        # Laravel on :8000
```

Then:

| Address | What |
|---|---|
| http://localhost/market/list | the shop — every click is an event |
| http://localhost/admin | the panel (`manager@example.com` / `password`) |
| http://localhost/api/v1/events | the API (GET needs the key) |

More commands and troubleshooting: [`docs/COMMANDS.md`](docs/COMMANDS.md).
Where the project stands and what comes next: [`docs/PLAN.md`](docs/PLAN.md).
