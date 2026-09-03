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

Go + Gin  →  MySQL. Step 5: wiring the service to the database.

# Mentor's curriculum (from raw notes below, ordered + current status)

1. [~] **Event data model** — research fields and types. `event_id` (UUID,
   always unique), `user_id`. This is a DB table structure; requests are
   built from it, then inserted. Think about which fields are meaningful.
   - Fixed by mentor: `event_platform` (app/web/tablet), `event_domain`
     (site data came from), `event_source` (listing/detail/cart/...).
   - `schema.sql` + `models/event.go` exist. NOT locked yet — see "Next".
2. [x] **Go + Gin service**, latest version. POST, GET, pull params, print,
   experiment. Docker later if it's in the way.
   - `/ping`, `/hello`, `POST /event` exist. Owe a "why Gin?" write-up.
3. [~] **MySQL** — install, create table, dummy data, try `SELECT`.
   - `docker-compose.yaml` (mysql:8.4) exists. `schema.sql` never loaded.
     DB has not been run yet.
4. [x] **Go MySQL library** — chose `sqlx` + `go-sql-driver/mysql`
   (+ `godotenv` for `.env`). Owe a "why sqlx?" write-up.
5. [ ] **Wire it together** — POST from Postman → Gin handler → INSERT.
   Mentor's toy version is `customer(id, name, surname)`; we're doing it
   straight on `events`.
6. [ ] **PHP admin panel** — later phase, mentor has separate notes.

If time left: `update` / `delete` / `get` endpoints, REST-ish paths
`api/v1/customer/{create,get,update,remove}`, all POST.

# Friday deliverable (mentor)

- [ ] Synthetic event dataset matching the locked model.
- [ ] Script that turns the dataset into `POST /event` requests.

# Next (smallest steps, in order)

1. **Lock the data model.** Reconcile `models/event.go` ↔ `db/schema.sql`
   ↔ mentor's field list. Decide:
   - `user_id` on the `events` row, or a separate linked table? (mentor
     floated a separate id table)
   - who generates `event_id` — client, or server-side UUID?
   - `event_timestamp` — server-set, or lean on the DB `DEFAULT`?
   - kill or rewrite `db/seed.sql` (currently a 3-column scratch table
     that it also drops at the end).
2. **Run MySQL.** `docker compose up db`, load `schema.sql` into
   `events_db`, confirm the app connects.
3. **Make `POST /event` correct.** Reject bad JSON (400 + `return`),
   201 on success, `json.Marshal` the payload before `Exec`, settle
   timestamp handling.
4. **Synthetic dataset + request script** (Friday item).

# Done / I can explain this

- (things you've actually understood, not just typed)

# Open questions for mentor

- Separate `user` / id table, or flat `events` row?
- Is `user_id` required, or nullable for anonymous events?
- Does the demo `events` schema carry into the full project, or is a
  rethink expected later?

# Demo shortcuts (already true in the code — revisit later)

- DSN host hardcoded to `127.0.0.1:3306` in `config/db.go`.
- `event_id` trusted from the client; no server-side UUID.
- `CreateEvent` ignores the JSON bind error and sends no success body.
- `db/seed.sql` is throwaway scratch, not aligned with `schema.sql`.

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
