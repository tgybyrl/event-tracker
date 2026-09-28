# Build history

How the project got to where it is, and why each piece is the way it is.
Moved out of `docs/PLAN.md` on 2026-09-28 so that PLAN.md can hold only the
current state and the road ahead. The text below is kept as it was written
at the time; paths and counts in it describe the project **as it was then**
(for example `/events` before the panel moved under `/admin`, the `Event`
model before the panel switched to the Go API, 43 rows before real traffic).
For the current state, read PLAN.md; for the reasoning in short form, read
DECISIONS.md.

---

# Status notes, 2026-09-04 → 2026-09-28

Step 6 — the Laravel admin panel — is **done**. Phases A (scaffold + shell),
B (events list), C (filters), D (login + roles) and E (user management) all
closed; phase E landed on `main` at `eb64e74` on 2026-09-22. See "Panel
phases" below.

Cleared on 2026-09-23, before starting Redis (see "Done on 2026-09-23" below):
the Go binaries moved under `backend/cmd/`, `event_timestamp` is now the
client's time, `POST /event` validates its body, and both panel screens that
showed nothing — the dashboard and `/settings` — are built.

On 2026-09-25 (branch `panel-via-api`, see "Done on 2026-09-25"): the panel
stopped reading MySQL for events and calls Go's read API instead, and moved
its own tables into a separate `panel_db` with a MySQL user that cannot see
`events_db`. `POST` moved to `/api/v1/events`.

On 2026-09-28 (branch `market`, see "Done on 2026-09-28"): everything answers
on **one address, http://localhost**, behind a Caddy reverse proxy — the shop
at `/market`, the API at `/api`, the panel at `/admin` — and a demo shop sends
**real events** from real clicks. The synthetic generator still exists but is
no longer the main source.

Branch per phase, merged into `main` when the phase closes. Phase A landed
on `main` at `4d53479`; phase B follows from `panel-phase-b`. Each merge is
a fast-forward, since a phase branch only ever moves ahead of `main`.
Phase E broke the habit once — the work was committed straight onto a
`panel-phase-e` branch created *after* the code was already written, rather
than before it. Same result, but the branch was not protecting anything
while the work happened.

Steps 1–5 (Go + Gin → MySQL) are done: `POST /event` works end to end
(bind error handled, payload stored as real JSON, DB fills the timestamp,
201 response). Friday deliverable done: synthetic dataset generator
(`backend/cmd/generate`, with per-action `event_payload`) and request
script (`backend/cmd/send`) both built and run end to end — 20/20 events
posted with `201`, confirmed with `SELECT COUNT(*) FROM events;`.

# Mentor's curriculum (from raw notes, ordered + status)

1. [x] **Event data model** — research fields and types. `event_id` (UUID,
       always unique), `user_id`. This is a DB table structure; requests are
       built from it, then inserted. Think about which fields are meaningful.
   - Fixed by mentor: `event_platform` (app/web/tablet), `event_domain`
     (site data came from), `event_source` (listing/detail/cart/...).
   - Locked: 9 columns, `schema.sql` == `models/event.go` == INSERT in
     `controllers/event.go`. `event_location` dropped (geo lives in
     `event_payload`), `user_ip` added, `user_id` is nullable (`*int`).
2. [x] **Go + Gin service**, latest version. POST, GET, pull params, print,
       experiment. Docker later if it's in the way.
   - `/ping`, `/hello`, `POST /event` exist. Owe a "why Gin?" write-up.
3. [x] **MySQL** — install, create table, dummy data, try `SELECT`.
   - `docker compose up -d db` (mysql:8.4), `schema.sql` loaded into
     `events_db` by hand, `DESCRIBE events` confirms 9 columns, app
     connects (`sqlx.Connect` = Open + Ping, no `log.Fatal`).
   - Dummy rows inserted via `POST /event` (real request path, not a
     manual `INSERT`), confirmed with `SELECT * FROM events;`.
4. [x] **Go MySQL library** — chose `sqlx` + `go-sql-driver/mysql`
       (+ `godotenv` for `.env`). Owe a "why sqlx?" write-up.
5. [x] **Wire it together** — POST from Postman → Gin handler → INSERT.
       Mentor's toy version is `customer(id, name, surname)`; we're doing it
       straight on `events`.
   - `ShouldBindJSON` error now checked (400 + return), `event_payload`
     switched from `any` to `json.RawMessage` (matches MySQL `JSON`
     column without a marshal round-trip), `event_timestamp` dropped
     from the INSERT (DB `DEFAULT` fills it), `return` added after the
     500 case, 201 + `event_id` returned on success, debug `fmt.Println`
     removed. *(2026-09-23: `event_timestamp` is back in the INSERT as
     `COALESCE(?, CURRENT_TIMESTAMP)` — see "Done on 2026-09-23".)*
6. [x] **PHP admin panel** — Laravel, event tablosunu listeleme ve filtreleme
       olucak. Ekstradan kullanıcı verilicek, admin panelden verilicek. Full
       laravel yapıları kullanılıcak. Her şey panelden yöneltilicek db
       bilgilerini .env verilicek.
   - Done. Phases A–E below. Every clause of the note is covered: listing
     and filtering (B, C), users given from the panel (E), `.env` holds the
     DB credentials (A). "Full laravel yapıları" is the reason auth uses
     Gates and `Auth::attempt` rather than a package, and why phase E uses
     `Route::resource` + FormRequests rather than hand-rolled routes.

If time left (mentor's note): `update` / `delete` / `get` endpoints, REST-ish
paths `api/v1/customer/{create,get,update,remove}`, all POST. *(Not done as
such: the project went straight to `events`, and `/api/v1` now serves events.)*

# Friday deliverable (mentor)

- [x] Synthetic event dataset matching the locked model.
      `backend/cmd/generate` (`go run ./cmd/generate`) writes
      `backend/events.json` — random platform/domain/source/action per
      event via `randomChoice`, `UserID`/`UserIP` randomly nil-or-set to
      represent anonymous vs. logged-in events. `event_payload` now varies
      per `event_action` via an `actionPayloads` map + `payloadFor()`
      lookup — panics if an action is missing from the map (deliberate:
      catch a drifted `actions`/`actionPayloads` pair at generation time,
      not silently).
- [x] Script that turns the dataset into `POST /event` requests.
      `backend/cmd/send` (`go run ./cmd/send`) reads `events.json`,
      POSTs each event to `http://127.0.0.1:8080/api/v1/events` (was `/event`
      until 2026-09-25), prints
      status/result per request plus a sent/failed summary. Verified: 20/20
      `201`, `SELECT COUNT(*)` confirmed rows landed.

# Panel phases (step 6)

Lives in `admin/`, sibling to `backend/`. Laravel 13.32, PHP 8.5,
Tailwind v4 + Vite (both ship with Laravel 13 — only `npm install` added).
Design follows the reference dashboard screenshot: white sidebar, blue
active pill, white content card, Plus Jakarta Sans, pill badges.

- [x] **A — scaffold + shell.** `composer create-project laravel/laravel
    admin`, `admin/.env` pointed at `events_db`, `php artisan migrate`
      (adds `users`/`sessions`/`cache`/`jobs`/`migrations` next to
      `events`), `npm install`.
      Built: `resources/css/app.css` (design tokens in `@theme`),
      `components/layouts/app.blade.php` (page shell),
      `components/partials/{sidebar,topbar}.blade.php`,
      `components/{badge,card,button,stat-card,empty-state}.blade.php`,
      `resources/js/app.js` (off-canvas sidebar under `lg`).
      Verified at 430/900/1280px — no horizontal overflow, drawer opens,
      focus ring visible.
- [x] **B — events list.** `/events`, newest first, 25 per page.
      `app/Models/Event.php` tells Eloquent how to fit a table it did not
      generate: `$table`, `$primaryKey = 'event_id'`, `$incrementing = false`,
      `$keyType = 'string'`, `$timestamps = false`, and casts
      (`event_payload` → array, `event_timestamp` → datetime).
      `EventController@index` orders by `event_timestamp DESC, event_id ASC`
      — the second key is required, not cosmetic: rows arrive in batches and
      land on the same timestamp (the largest group is 20 of the 43 rows at
      `2026-09-15 09:12:57`), and MySQL gives no stable order for ties, so
      without a tie-break a row can show up on page 1 and again on page 2. `->withQueryString()` is already on the paginator so phase C
      filters survive a page change.
      `views/events/index.blade.php` reuses phase A components only, with
      value→badge-tone maps in a `@php` block, payload in a native
      `<details>` (no JS), and a hand-rolled pager (Laravel's `links()`
      ships `gray-*`/`dark:` classes that ignore the design tokens).
      Verified: 25 + 18 = 43 rows, zero overlap between pages, payload
      decodes, empty state at `?page=99`.
      Also removed the topbar's free-text search — nothing read `?q=`, so it
      was a control that did nothing. The events table is better narrowed by
      its known columns, which is phase C.
- [x] **C — filters.** Four column filters (`event_platform` /
      `event_domain` / `event_source` / `event_action`) plus a `from`/`to`
      date range, all read from the query string. No filter class — one
      `when()` call per column in `EventController@index`, which emits no
      `WHERE` at all for an unset filter. Date bounds use `whereDate` so `to`
      includes the whole day instead of cutting at midnight.
      `$request->validate()` runs first: `per_page` is allowlisted to
      25/50/100 (without it `?per_page=999999` pulls the whole table into one
      page), `to` is `after_or_equal:from`, and the four string filters are
      capped at their `schema.sql` column widths. The filters themselves carry
      no injection risk — Eloquent binds them as parameters. `validate()`
      returns only keys that were present in the URL, so `$filters += [...]`
      fills the rest and the view never needs `isset()`.
      Dropdown options come from `SELECT DISTINCT` on the table, not a
      hardcoded list mirroring `cmd/generate/main.go`: a new action Go
      starts sending appears in the filter with no PHP change. Cost logged
      under Known shortcuts.
      UI matches the reference screenshot: a toolbar reading
      `Showing [25 ▾] per page` on the left, a `▽ Filter` button on the right,
      and a field panel below it. The panel is a `<div hidden>` toggled from
      `app.js`, **not** `<details>` — `<summary>` must be the first child of
      `<details>`, which would drag the per-page select into the toggle row
      and make clicking the select open and close the panel. Panel starts open
      when any filter is set or validation failed, so a filtered URL and an
      error both show their own state.
      Page size applies on `change` (a lone select with no submit beside it
      reads as broken); the filter fields wait for Apply, because changing
      four of them should be one request, not four.
      Empty state splits in two: "No events match these filters" + Clear,
      versus phase B's "No events yet". Without the split, filtering to zero
      rows tells you to go run `cmd/send` against a table that already has
      43 rows.
      Reference also had an Export button; deliberately skipped, nothing asks
      for CSV yet.
      Verified: `?platform=web` → 14 rows, `?platform=web&action=add_to_cart`
      → 3, both matching `SELECT COUNT(*)`; `?per_page=999` bounces back with
      the message in the panel; `?from=2027-01-01` hits the filtered empty
      state. Date range can only be demoed as "all" or "nothing" until the
      generator writes spread-out timestamps — all 43 rows share one.
- [x] **D — login + roles.** *(Was phase E. Swapped on 2026-09-21: phase E's
      user management is itself a screen only a manager may open, so auth has
      to exist before it, or it gets built open and gated afterwards — twice
      the work. Nothing was built under the old letters, so nothing to rename
      in the history.)*
      `add_role_to_users_table` adds `role VARCHAR(20) DEFAULT 'worker'`.
      Writing that migration is allowed — `users` is Laravel's own table;
      `events` is still off limits. The default is `worker` on purpose:
      forgetting to set a role gives the *least* access, not the most.
      `User::isManager()` holds the one comparison against `'manager'`, so
      the string is not repeated across the Gate, the sidebar and phase E.
      Plain string, not a PHP enum — two values did not earn a new concept.
      `DatabaseSeeder` creates `manager@example.com` and `worker@example.com`
      with `updateOrCreate`, so re-seeding leaves two rows, not four. The
      first manager has to come from a seeder: you cannot create it through a
      screen only a manager may open. The worker exists so the 403 path can be
      demonstrated without hand-editing the database.
      `AuthController` is `create`/`store`/`destroy`. Three things in it are
      not cosmetic: `session()->regenerate()` after a successful attempt
      (session fixation — an id planted before login would otherwise still be
      valid after it); one generic "These credentials do not match our
      records" for both a missing email and a wrong password (telling them
      apart confirms which emails have accounts); and `throttle:5,1` on
      `POST /login`, without which the form is an open password oracle.
      `redirect()->intended('/')` sends you back to the page `auth` bounced
      you off — verified: `/events?platform=web` survives the login.
      Authorization is Laravel's built-in `Gate`, defined in
      `AppServiceProvider::boot()` (Laravel 11+ dropped `AuthServiceProvider`),
      not `spatie/laravel-permission`: two roles do not justify a dependency,
      and "full laravel yapıları kullanılıcak" is exactly what Gates are. One
      `manage-users` definition feeds three consumers — `can:manage-users` on
      the route, the sidebar's `@continue`, and phase E's controller.
      `/users` is a stub screen so the Gate protects something real and can be
      shown working; phase E swaps the `Route::view` for a `Route::resource`.
      Deliberately NOT Breeze — it generates its own Blade and Tailwind
      layouts that would fight the phase A design. The login page instead gets
      `layouts/auth.blade.php`, a second layout rather than a variant of
      `layouts/app`: that one is a sidebar plus a topbar, and neither means
      anything to someone who is not logged in yet.
      The sidebar hides the Users item behind the same Gate — a link that
      always 403s is a broken control, not a security feature; the route's
      Gate is the actual protection. Both shortcuts owed from phase A are
      paid off here: the topbar reads `auth()->user()`, and `GET /logout`
      is now a POST form with `@csrf` (a GET logout can be fired by any
      `<img>` tag on a page the user happens to visit).
      Verified: logged out, `/`, `/events` and `/users` all 302 to `/login`;
      wrong password re-renders with the generic message and the email still
      filled in (`old('email')`), password never; 6th attempt in a minute is
      `429`; worker gets 200/200/**403** on `/`,`/events`,`/users` with no
      Users link, manager gets 200 everywhere with the link; the pre-login
      row in `sessions` is gone after login and the new one carries
      `user_id=1`; `GET /logout` is 405, POST without a token is 419, with one
      is a 302 to `/login` and `/events` bounces again afterwards; phase C
      still returns 43 / 14 / 3 rows for no filter / `?platform=web` /
      `?platform=web&action=add_to_cart`, matching `COUNT(*)`.
- [x] **E — users from the panel.** *(Was phase D.)* CRUD on `users`, behind
      the `manage-users` Gate. One `Route::resource(...)->except('show')`
      replaces the phase D `Route::view` stub and makes six routes with the
      conventional names; the Gate sits on the resource, so the *writes* are
      protected, not just the screens — verified with a worker session
      getting 403 on `POST /users` and `DELETE /users/{id}` directly, not
      only on the pages. `show` is excluded: a read-only page for four
      fields the list already prints is a screen with nothing on it.
      Validation moved into `StoreUserRequest` / `UpdateUserRequest` rather
      than an inline `validate()` like `EventController`, because the rules
      differ between create and update. Two differences are not cosmetic:
      the update's email rule is
      `Rule::unique('users','email')->ignore($this->route('user'))` —
      without the ignore, saving the form without touching the email fails
      against the row's own address — and its `password` is `nullable`, with
      the controller dropping the key when blank, or the `hashed` cast would
      store a hash of the empty string. Each request's `authorize()` re-checks
      the Gate, so the rules cannot be reached by a route that forgot the
      middleware.
      `User::ROLES` holds the two valid roles. The column is a bare
      `VARCHAR(20)` with no constraint behind it, so that constant is the only
      thing rejecting `role=owner`; both FormRequests and both `<select>`s
      read it, so the rule and the dropdown cannot drift.
      Two guards live in `UserController`, not the FormRequests, because they
      depend on *which row* is being changed rather than on the values sent:
      a manager may not demote or delete themselves. Together they also mean
      the panel can never reach zero managers — you can only ever delete
      someone else, so the last manager standing is whoever is holding the
      keyboard. The edit form disables the role select for your own row and
      resends the value in a hidden field, but that is cosmetic; the
      controller check is the protection, and it was tested by hand-crafting
      the `PUT` that the disabled select was supposed to prevent.
      Also added, and deliberately committed separately because neither is
      specific to users: flash messages in `layouts/app.blade.php` (a write
      redirects to a different page than the form, so the banner has to live
      where the redirect lands — `status` for a completed write, `error` for
      a refused one) and a `danger` variant on `x-button`.
      Verified: worker 403 on all four user routes including the two writes;
      duplicate email + `role=owner` + a 6-character password all three come
      back on the form; blank password leaves the hash byte-identical while a
      filled one changes it and logs in with the new value; hand-crafted
      self-demote `PUT` refused with the row unchanged; self-delete refused;
      phase C still returns 43 / 14 / 3 rows.

# Panel: dashboard and settings (scoped 2026-09-22, built 2026-09-23)

Neither is in the mentor's notes. They exist because the reference dashboard
screenshot had them and phase A built the shell. Scoped here so they get
built deliberately rather than filled with whatever the screenshot had.

## Dashboard (`/`)

**Built 2026-09-23** (`7c6550e`), after the timestamp fix it was blocked on.
`DashboardController@index`; the Events-by-action list uses
`<x-action-badge>`, extracted from the events list (`b8efefc`) so an action
reads as the same colour on both screens, and each row links to `/events`
filtered to that action. "Events this week" shipped as a rolling
"Last 7 days". The scoping below is kept as written.

Replace `Route::view('/', 'dashboard')` with a `DashboardController@index`.
Four cards:

| Card | Query | Why it earns the space |
| --- | --- | --- |
| Events today | `WHERE DATE(event_timestamp) = CURDATE()` | Answers "is the pipeline alive" |
| Events this week | last 7 days | Context — today's number means nothing without the usual |
| Anonymous share | `user_id IS NULL` over `COUNT(*)`, as a % | Data-quality signal: a jump means login tracking broke on the client |
| Last event received | `MAX(event_timestamp)`, shown as "4 minutes ago" | The only card that is **buildable today** |

That last card is worth understanding rather than copying. `event_timestamp`
holding insert-time is *wrong* for "events today" but exactly *right* for
"when did we last receive anything" — so it is correct before the timestamp
fix and stays correct after it. It is also the most useful card on the
screen: if it reads "3 days ago" something is broken, and no other card says
so.

Below the cards, replace the `<x-empty-state>` with one
`GROUP BY event_action` count list. One query, reuses `<x-badge>`, shows what
people actually do. **No chart library** — that would be a dependency added
for a demo, and the rule is no new dependencies without a reason.

Cost: about five queries on a table with no indexes. Fine at 43 rows, already
logged under Known shortcuts, and worth saying out loud rather than hiding.

## Settings (`/settings`)

**Built 2026-09-23** (`61ecd63`). `SettingsController` edit/update plus
`UpdateSettingsRequest`; all three rules below hold, and email and role are
shown read-only. The scoping below is kept as written.

The sidebar has linked here since phase A and it 404s. The thing worth
putting behind it is **your own account**: change your own name and password,
nothing else.

The justification is a real gap phase E left, not a desire to fill a nav
item: **a worker cannot change their own password.** Managers can edit
themselves at `/users/{id}/edit`; workers cannot reach `/users` at all, so
today a forgotten worker password means re-running the seeder. That is
already in the Known shortcuts list.

Three rules fall straight out of phase E and none of them is optional:

- It edits `auth()->id()` only — **never** an id taken from the URL, or this
  is `/users` again with the Gate removed.
- **No role field.** Editing your own role is precisely what phase E forbids,
  and this screen has no Gate in front of it.
- Require the current password before setting a new one. Laravel ships a
  `current_password` rule, so nothing new to install. Without it, a borrowed
  unlocked laptop turns into a permanent account takeover.

No Gate on the route: every logged-in user gets their own account screen. The
sidebar item therefore needs no `can` key, unlike Users.

Deliberately **not** going here: theme toggles, notification preferences, app
configuration. None has a consumer. Building them recreates the bell that was
removed on 2026-09-22 for exactly that reason.

# Done on 2026-09-23

Nine commits, `ab6a8a7`..`b528b0d`, one per step, plus the docs commit that wrote this.

- **`backend/cmd/`.** The three `package main` directories now sit under
  `cmd/api`, `cmd/generate`, `cmd/send`. Only files moved; `config/`,
  `controllers/`, `models/` stay put, no `pkg/`, no `internal/` — nothing
  outside this module imports them. Still run from `backend/`, because
  `.env` and `events.json` are resolved from the working directory.
- **`event_timestamp` is the client's time.** `models.Event.Timestamp` is
  `*time.Time` (a plain `time.Time` cannot say "not sent" — its zero value is
  year 0001), and the INSERT uses `COALESCE(?, CURRENT_TIMESTAMP)`, so a body
  without one gets the database clock exactly as before. Everything runs in
  UTC: driver default, MySQL container, Laravel.
- **Generator** spreads events over the last 14 days, makes 200 of them,
  draws `user_id` from 1..1000, emits real v4 UUIDs (version/variant bits
  set by hand, no new dependency), and stops on errors instead of writing an
  empty `events.json`. 200/200 posted with `201`; the table now covers 15
  calendar days.
- **`POST /event` validates.** `binding` tags on `models.Event` (UUID
  `event_id`, required columns with `max=` matching `schema.sql`, `ip` on
  `user_ip`); payload must be a JSON object; a timestamp more than a minute in
  the future is a 400; a duplicate `event_id` is a 409; other DB errors are
  logged and answered with a generic 500.
- **Dashboard** and **`/settings`** built — see the section above.
- `admin/.env.example` names MySQL and `events_db` instead of SQLite.

# Done on 2026-09-25 (branch `panel-via-api`)

- **API under `/api/v1`.** `POST /api/v1/events` replaces `POST /event`
  (removed, not aliased — nothing outside the repo called it).
- **Read endpoints, behind a key.** `GET /api/v1/events` (filters, page,
  per_page; answers `data` + `meta`), `GET /api/v1/events/facets` (distinct
  values per filter column), `GET /api/v1/events/stats` (dashboard counts in
  one pass over the table). `middleware.RequireAPIKey` checks
  `Authorization: Bearer <EVENTS_API_KEY>` with a constant-time compare; the
  server refuses to start with the key unset. `POST` stays public — the
  market page will call it from a browser.
- **Filter SQL** is built from fixed column names only; every value is a `?`
  argument. Date bounds compare the plain column (`>= from`, `< to + 1 day`),
  so a future index on `event_timestamp` can be used.
- **Panel reads through Go.** `app/Services/EventsApi.php` is the only way the
  panel reaches event data; `app/Models/Event.php` is deleted. Views were not
  touched: the list gets a hand-built `LengthAwarePaginator`. Go down, slow or
  refusing the key → one 503 page naming the service, and the error is logged.
- **Panel database.** `panel_db` + a `panel` MySQL user with no rights on
  `events_db`, created by `db/panel-db.sh`. `docker-compose.yaml` mounts
  `schema.sql` and that script into `/docker-entrypoint-initdb.d`, so a fresh
  volume builds everything from one `docker compose up`. Locally the three
  panel accounts were copied across unchanged and the nine Laravel tables
  dropped from `events_db`, which now holds only `events`.

# Done on 2026-09-28 (branches `market`, `session-per-user`)

- **Panel under `/admin`.** `Route::prefix('admin')` around every route; the
  eleven hand-written paths (sidebar, two buttons, the post-login fallback)
  now go through `route(...)`.
- **One address: Caddy.** `proxy/Caddyfile`, run as the `proxy` service in
  compose (`caddy:2.11.4-alpine`, port 80). `/api/*` → Go :8080, `/admin*` and
  `/build/*` → Laravel :8000, `/market/*` → static files, `/` →
  `/market/list`, anything else 404. The `proxy/` folder is mounted, not the
  single file — a single-file mount kept serving the old Caddyfile after an
  edit. Laravel trusts `X-Forwarded-*` so its links say `localhost/admin`.
  Market and API share an origin, so Go needed **no CORS code**.
- **The shop, "BLO"** (first called "pasaj"; the name is `SHOP_NAME` in `market/js/shop.js`). `market/`: plain HTML/CSS/JS, no build step.
  Listing (reyon / category / search from the URL), product page (colour
  swatches, size grid, a size is required), cart (quantity, remove, "complete
  order" with no payment). Layout follows the flo.com.tr reference the user
  shared, but no logo, photo or real brand is copied: products are SVG
  drawings (`js/art.js`) with invented brands, read from `products.json`.
- **The tracker.** `market/js/tracker.js` listens to the shop's browser
  events and posts `page_view`, `product_click`, `add_to_cart` and
  `checkout_start`, with `session_id` inside `event_payload` (30 idle minutes
  end a session). A product click holds navigation up to 300 ms so the event
  is not lost. The **Events** button shows every event the tab sent and the
  API's answer. Checked end to end by driving headless Chrome: six clicks,
  six 201s, six rows with one session id.
- `page_view` got its own badge colour in the panel; the filter and the
  dashboard picked the new action up with no code change.
- **Who and where.** The market got a demo login (three customers,
  `user_id` 2001-2003), and Go now fills `user_ip` from the connection when
  the body has none, trusting `X-Forwarded-For` only from the proxy
  (`TRUSTED_PROXIES`). Before this every market event was anonymous and
  address-less.
- Caddy sends `Cache-Control: no-cache` on `/market`, after a stale cached
  `tracker.js` kept sending `user_id` null.
- **A new session per person** (`session-per-user`): a session now also ends
  when the customer changes (logout, or another customer logs in); logging in
  from anonymous still keeps it.
- **Dashboard charts** (`dashboard-charts`): `/events/stats` gained
  `hourly` (24 buckets), `daily` (14 days, with anonymous counts), `funnel`
  (visits per step, last 7 days) and `timezone`, all counted on the
  Istanbul clock; empty buckets are zeros, not gaps. The panel draws them
  with three hand-made SVG components — columns, sparkline, funnel — with a
  24 hours / 14 days switch, pointer tooltips, the running bucket drawn
  pale and dashed, and a sideways-scrolling chart on phones that opens at
  "now".
  The same day the time chart moved to **Chart.js** (the agreed fallback):
  this period as a filled line against the period before as a grey line,
  a crosshair tooltip, a one-time draw-in animation; `/events/stats` gained
  a `previous` value per hourly and daily bucket for it.
  "Events by action" on the dashboard gave way to **Top products** (last 7
  days, market visits only, clicks and adds per product); for it the tracker
  now sends `product_name`, `brand` and `category` with every product event.
