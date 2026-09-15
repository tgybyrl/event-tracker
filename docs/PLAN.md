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

Go + Gin  →  MySQL. Step 5 done: `POST /event` works end to end (bind
error handled, payload stored as real JSON, DB fills the timestamp, 201
response). Dummy rows inserted and verified with `SELECT`. Friday
deliverable done: synthetic dataset generator (`backend/tools/generate`,
now with per-action `event_payload`) and request script
(`backend/tools/send`) both built and run end to end — 20/20 events
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
6. [ ] **PHP admin panel** — later phase, mentor has separate notes.

If time left: `update` / `delete` / `get` endpoints, REST-ish paths
`api/v1/customer/{create,get,update,remove}`, all POST.

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

1. `user_ip` from body vs. `c.ClientIP()` — still open, noted below too.

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
```
