# Scope right now: DEMO first, then full project

Mentor wants a small working demo before the full build.
Assumption (unconfirmed): the demo becomes the foundation, not throwaway.

- Keep it small. Shortest thing that runs and can be shown.
- Hardcoded values, missing error handling, no auth: fine for now.
- Data model and DB schema: NOT a shortcut area. Get these right the
  first time — they're expensive to change later.
- When I take a demo shortcut, note it under "Demo shortcuts" so I know
  what to revisit.

# Current phase

Step 6: the Laravel admin panel, in `admin/`. Go writes events, Laravel
reads them — the panel is the read side. Phases A (scaffold + shell),
B (events list), C (filters) and D (login + roles) are done; phase E
(user management) is next. See "Panel phases" below.

Phase D was built on the *provisional* screen-based access model — the
mentor has still not answered the "specific access" question. If the answer
turns out to be row-level, phase D's work stands and a join table plus one
`where` is added on top.

Branch per phase, merged into `main` when the phase closes. Phase A landed
on `main` at `4d53479`; phase B follows from `panel-phase-b`. Each merge is
a fast-forward, since a phase branch only ever moves ahead of `main`.

Steps 1–5 (Go + Gin → MySQL) are done: `POST /event` works end to end
(bind error handled, payload stored as real JSON, DB fills the timestamp,
201 response). Friday deliverable done: synthetic dataset generator
(`backend/tools/generate`, with per-action `event_payload`) and request
script (`backend/tools/send`) both built and run end to end — 20/20 events
posted with `201`, confirmed with `SELECT COUNT(*) FROM events;`.

# Mentor's curriculum (from raw notes below, ordered + current status)

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
     removed.
6. [ ] **PHP admin panel** — Laravel, event tablosunu listeleme ve filtreleme
       olucak. Ekstradan kullanıcı verilicek, admin panelden verilicek. Full
       laravel yapıları kullanılıcak. Her şey panelden yöneltilicek db
       bilgilerini .env verilicek.
   - In progress. Broken into phases A–E below.

If time left: `update` / `delete` / `get` endpoints, REST-ish paths
`api/v1/customer/{create,get,update,remove}`, all POST.

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
      — the second key is required, not cosmetic: all 43 seeded rows share
      one timestamp, and without a tie-break MySQL can repeat a row across
      pages. `->withQueryString()` is already on the paginator so phase C
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
      hardcoded list mirroring `tools/generate/main.go`: a new action Go
      starts sending appears in the filter with no PHP change. Cost logged
      under Demo shortcuts.
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
      rows tells you to go run `tools/send` against a table that already has
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
- [ ] **E — users from the panel.** *(Was phase D.)* CRUD on `users`:
      resource controller, `FormRequest` validation, `Route::resource`,
      behind the manager Gate from phase D. Replaces the `/users` stub
      (`Route::view` → `Route::resource`, `views/users/index.blade.php`
      becomes a real list). The `role` field needs an `in:manager,worker`
      rule — nothing validates it yet, because nothing writes it yet.
      A manager must not be able to demote or delete their own account, or
      the panel can be locked out of itself.

**Access model — screen-based, provisional.** Decided 2026-09-21, to be
confirmed with the mentor. A worker's access controls *what they can do*,
not *which rows they see*: everyone who can open `/events` sees every event.
So `users` needs a `role` column and nothing else — no `domain_user` table,
no per-row scoping, and no change to `events`.
The alternative, rejected for now, was row-level access tied to
`event_domain` (worker sees only `flo.com`, say). If the mentor wants that
later it is additive: a join table plus one `where` on the events query.
Phase C should be written without it either way.

**Do not write a migration for `events`.** Go owns that table and
`db/schema.sql` is the single source of truth. Laravel only reads it.

# Friday deliverable (mentor)

- [x] Synthetic event dataset matching the locked model.
      `backend/tools/generate` (`go run ./tools/generate`) writes
      `backend/events.json` — random platform/domain/source/action per
      event via `randomChoice`, `UserID`/`UserIP` randomly nil-or-set to
      represent anonymous vs. logged-in events. `event_payload` now varies
      per `event_action` via an `actionPayloads` map + `payloadFor()`
      lookup — panics if an action is missing from the map (deliberate:
      catch a drifted `actions`/`actionPayloads` pair at generation time,
      not silently).
- [x] Script that turns the dataset into `POST /event` requests.
      `backend/tools/send` (`go run ./tools/send`) reads `events.json`,
      POSTs each event to `http://127.0.0.1:8080/event`, prints
      status/result per request plus a sent/failed summary. Verified: 20/20
      `201`, `SELECT COUNT(*)` confirmed rows landed.

# Next (smallest steps, in order)

1. Still ask the mentor the "specific access" question — phase D shipped on
   the provisional screen-based answer. If it turns out to be row-level, the
   addition is a join table plus one `where`, not a rewrite. It is the last
   item under "Open questions for mentor".
2. Phase E: `Route::resource` on `users`, a resource controller and
   `FormRequest`s, behind the `manage-users` Gate that phase D built.
3. Still unticked from phase B's original list, deliberately deferred:
   replace `Route::view('/', 'dashboard')` with a controller that fills the
   four stat cards.
4. `user_ip` from body vs. `c.ClientIP()` — still open, noted below too.
5. Make `tools/generate` spread `event_timestamp` over a range of days.
   Not cosmetic: the date filter shipped in phase C cannot be demonstrated
   to the mentor while every row shares one timestamp.

# How to run the panel

```
docker compose up -d db          # MySQL must be up
cd admin && php artisan migrate  # once, after pulling phase D
cd admin && php artisan db:seed  # once — creates the manager and worker
cd admin && npm run dev          # Vite, leave running
cd admin && php artisan serve    # http://127.0.0.1:8000
```

Log in with `manager@example.com` / `password` (sees Users) or
`worker@example.com` / `password` (403 on `/users`).

# Done / I can explain this

- Request flow: JSON body → `gin.Context` → `ShouldBindJSON` → `Event`
  struct → `Exec` args → `?` placeholders → MySQL row.
- Side-effect imports (`_ "...mysql"`, `_ "...godotenv/autoload"`): imported
  only so their `init()` runs (driver registration / `.env` load).
- `godotenv/autoload` reads `.env` from the process working directory, so
  the app needs `backend/.env` when run via `cd backend && go run .`.
- Docker named volume `mysql_data` persists across `up`/`down`; `down -v`
  wipes it. That's why an old `events` table survived a schema change.
- `*int` / `*string` struct fields = nullable columns (`nil` → `NULL`).

# Open questions for mentor

- Separate `user` / id table, or keep `user_id` on the flat `events` row?
- `user_id` is nullable now (anonymous events). OK, or should it be required?
- Does the demo `events` schema carry into the full project, or is a
  rethink expected later?
- `event_action`: kept as its own column (not a payload key). Agree?
- Panel reads MySQL directly with Eloquent. In the real project, should it
  go through the Go API instead so Go stays the only thing touching the
  events table?
- Laravel's `users` table now lives in `events_db` alongside `events`.
  Same database, or should the panel get its own?
- The panel filters on `event_platform` / `event_domain` / `event_source` /
  `event_action` and none of them is indexed. Should `events` get indexes on
  those columns? It is an `ALTER TABLE` on Go's table, so it is your call,
  not the panel's.
- The panel is for a company's own staff: a manager account that grants
  access to worker accounts. When a manager gives a worker "specific"
  rather than general access — specific to what? Assumed screen-based
  (what you can do) and shipped phase D on that. The other reading is
  row-level (a worker sees only certain `event_domain` values), which needs
  a join table and one extra `where` on the events query. Still unanswered;
  if it is row-level, phase D's `role` column and Gate stay as they are and
  the join table is added on top.

# Demo shortcuts (already true in the code — revisit later)

- DSN host hardcoded to `127.0.0.1:3306` in `config/db.go`.
- `event_id` trusted from the client; no server-side UUID.
- `CreateEvent` ignores the JSON bind error and sends no success body.
- `db/seed.sql` is throwaway scratch, not aligned with `schema.sql`.
- `.env` exists twice: repo root (for `docker compose`) and `backend/`
  (for `godotenv/autoload`). Kept in sync by hand.
- `user_ip` currently comes from the request body, not `c.ClientIP()`.
- Gin logs "You trusted all proxies" — no trusted-proxy list set.
- `schema.sql` loaded manually, not via a compose `initdb` mount.
- `.env` now exists three times: repo root, `backend/`, and `admin/`.
- The panel connects to MySQL as `root` with the same password the
  container uses. A real deployment needs a read-only panel user.
- Seeded panel accounts use the hardcoded password `password`. Fine for a
  demo on localhost; a real deployment needs the first manager created with
  a password that was never written down in the repo.
- No password reset, no email verification, no "remember me". The
  `password_reset_tokens` table exists (Laravel created it) and nothing
  uses it. A forgotten password means re-running the seeder.
- `/users` is a stub screen. It exists so the `manage-users` Gate protects
  a real route; phase E fills it in.
- Nothing validates the `role` value yet — the column accepts any string up
  to 20 chars. Only the seeder writes it today; phase E's `FormRequest`
  adds `in:manager,worker`.
- `throttle:5,1` on `POST /login` keys on IP. Behind a proxy every request
  would look like one IP; same family of problem as Gin's untrusted-proxy
  warning above.
- `Route::view('/', 'dashboard')` renders the shell with no data behind
  it; the four stat cards still show `—`. `/events` reads the database,
  the dashboard does not.
- The panel's Eloquent model can write to `events` even though nothing
  does — no `$fillable`, but nothing stops `Event::query()->update(...)`.
  Go is meant to be the only writer; a read-only DB user would enforce it.
- The events list runs four `SELECT DISTINCT` queries per page load to fill
  the filter dropdowns — five queries total where phase B had one. Fine at
  43 rows; at scale these want caching or a lookup table.
- No index on any filtered column. Every filter is a full table scan, and so
  is every `DISTINCT`. Adding one means an `ALTER TABLE` on `events`, which
  Go owns — mentor question, not a panel change.
- `event_timestamp` is identical across all 43 seeded rows, so the date
  filter is untestable beyond "all" and "nothing" until `tools/generate`
  spreads timestamps out.

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
