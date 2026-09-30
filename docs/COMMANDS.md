# Sık kullanılan komutlar

Projede çalıştırdığımız komutlar. Yeni komut kullandıkça buraya eklenecek.

---

## Projeyi ayağa kaldırma

Dört terminal gerekiyor, bu sırayla:

```
docker compose up -d                # 1. MySQL + Redis + Caddy proxy (port 80)
cd backend && go run ./cmd/api      # 2. Go servisi   → :8080
cd backend && go run ./cmd/worker   # 3. Worker: kuyruktaki event'leri MySQL'e yazar
cd admin && php artisan serve       # 4. Laravel      → :8000
```

Worker çalışmıyorsa mağaza yine `202` alır ama event'ler Redis'te bekler, panelde görünmez. Worker açılınca birikenleri yazar.

Sonra her şey tek adreste, **http://localhost**:

| Adres | Ne |
|---|---|
| `http://localhost/market/list` | Demo mağaza (BLO). Tıklamalar event olarak gider |
| `http://localhost/admin` | Panel |
| `http://localhost/api/v1/events` | Go API (GET için anahtar gerekir) |

`:8000` ve `:8080` hâlâ doğrudan da açılabilir, ama asıl giriş kapısı proxy.

```
docker compose up -d proxy                              # sadece proxy'yi başlat
docker compose exec proxy caddy reload --config /etc/caddy/Caddyfile   # Caddyfile değişince
docker compose logs -f proxy                            # proxy loglarını izle
```

Panelde geliştirme yapıyorsan beşinci bir terminalde Vite de açık olmalı:

```
cd admin && npm run dev          # → :5173
```

---

## Docker / MySQL

```
docker compose up -d db
```
`db` servisini (MySQL) arka planda başlatır. `-d` = detached, terminali kilitlemez.

```
docker compose ps
```
Container çalışıyor mu gösterir.

```
docker compose logs -f db
```
MySQL loglarını canlı izler. Bağlantı hatası alırsan ilk buraya bak. `-f` = follow, çıkmak için `Ctrl+C`.

```
docker compose stop db
```
Container'ı durdurur, veri ve container durur ama silinmez.

```
docker compose down
```
Container'ı siler. **Veri silinmez** — `mysql_data` volume'unda kalır.

```
docker compose down -v
```
Container'ı **ve veriyi** siler: MySQL'in tabloları ve satırları (`mysql_data`) ile Redis'teki kuyruk (`redis_data`). Sıfırdan schema yüklemen gerekir.

### Veritabanlarının kurulumu

Boş bir volume ile ilk `docker compose up -d db` her şeyi kendisi kurar. `docker-compose.yaml`, iki dosyayı container'ın `/docker-entrypoint-initdb.d/` klasörüne bağlıyor ve MySQL bunları volume boşken **bir kez**, isim sırasıyla çalıştırıyor:

1. `01-schema.sql` = `db/schema.sql` → `events_db` içinde `events` tablosu.
2. `02-panel-db.sh` = `db/panel-db.sh` → `panel_db` veritabanı ve `panel` MySQL kullanıcısı. `panel` sadece `panel_db`'ye erişebilir, `events_db`'ye hiç erişemez.

Önce kök dizindeki `.env` dosyasını `.env.example`'dan oluştur. `PANEL_DB_PASSWORD` dolu olmalı, yoksa script durur.

Volume **zaten doluysa** bu dosyalar çalışmaz. O durumda panel kurulumunu elle bir kez çalıştır:

```
docker compose up -d db                                          # yeni env ile container'ı yeniden oluştur
docker compose exec db sh /docker-entrypoint-initdb.d/02-panel-db.sh
cd admin && php artisan migrate                                  # panel tablolarını panel_db'de oluştur
```

Script tekrar çalıştırılabilir, her satırı `IF NOT EXISTS`.

`events` tablosunu elle yüklemen gerekirse (eski yöntem):

```
docker compose exec -T db mysql -u root -pSIFRE events_db < db/schema.sql
```
`-p` ile şifre arasında **boşluk yok**. `-T` = TTY açma, dosya yönlendirmesi (`<`) ile çalışması için gerekli.

### MySQL client'a girme

```
docker compose exec db mysql -u root -p
```
Container'ın içine girip MySQL client'ı açar. Şifreyi sorar (`.env` → `MYSQL_ROOT_PASSWORD`). Çıkmak için `exit`.

Client açıldıktan sonra:

```sql
USE events_db;                  -- veritabanını seç
SHOW TABLES;                    -- hangi tablolar var
DESCRIBE events;                -- events tablosunun kolonları
SELECT COUNT(*) FROM events;    -- kaç satır var
SELECT * FROM events LIMIT 5;   -- ilk 5 satır

-- Panel filtrelerini doğrulamak için:
SELECT COUNT(*) FROM events WHERE event_platform = 'web';
SELECT DISTINCT event_action FROM events;
```

### Redis: kuyruğa bakma

```
docker compose exec redis redis-cli
```
Redis'in kendi client'ı. Şifre yok (Redis sadece bu makineye açık). Komutlar büyük/küçük harf fark etmez; çıkmak için `exit`.

```
XLEN events                          # stream'de kaç entry var (onaylanmış olanlar dahil)
XINFO GROUPS events                  # grup "ingest": pending = verilmiş ama onaylanmamış,
                                     #   lag = henüz hiçbir worker'a verilmemiş
XINFO CONSUMERS events ingest        # hangi worker'lar kayıtlı, kimde kaç pending var
XPENDING events ingest               # onaylanmamış entry sayısı, en eski/en yeni id
XREVRANGE events + - COUNT 3         # son 3 entry (event'in JSON'u "event" alanında)
XRANGE events:dead - +               # vazgeçilen entry'ler: asıl id, sebep, event
```

"Worker yetişiyor mu?" sorusunun cevabı `XINFO GROUPS events`'teki `lag`: sürekli büyüyorsa worker geride kalıyor, ikinci bir worker başlat.

Temizlik:

```
XTRIM events ACKED MAXLEN 0          # onaylanmış (MySQL'e yazılmış) entry'leri sil
DEL events:dead                      # dead stream'i sil
XGROUP DELCONSUMER events ingest <ad> # kapanmış bir worker'ın adını gruptan sil (pending'i 0 olmalı)
```

**`DEL events` yapma**: henüz MySQL'e yazılmamış event'ler de silinir, consumer group da gider ve çalışan worker yeniden başlatılana kadar okuyamaz.

```
docker compose stop redis            # Redis'i durdur → API 503 döner
docker compose start redis           # geri başlat; kuyruk ve grup diskten (appendonly) geri gelir
```

---

## Go

```
cd backend && go run ./cmd/api
```
`backend/cmd/api/` içindeki `package main`'i derleyip çalıştırır. Servisi `:8080`'de ayağa kaldırır. `.env`'i çalışma dizininden okuduğu için **`backend/` içinden** çalıştırmak zorunlu. MySQL ve Redis ayakta olmalı; biri yoksa açılışta hata verip durur.

```
cd backend && go run ./cmd/worker
```
Redis'teki `events` stream'ini okuyup MySQL'e yazar. 10 saniyede bir ne yaptığını basar (`stored`, `left for retry`, `dead`). Ctrl+C: elindeki batch'i bitirip 1 saniye içinde çıkar; ikinci Ctrl+C hemen öldürür. İki terminalde iki tane çalıştırılabilir, işi paylaşırlar.

```
go build ./...
```
Tüm paketleri derler ama çalıştırmaz. "Derleniyor mu?" kontrolü. `./...` = bu dizin ve altındaki her şey.

```
go vet ./...
```
Derlenen ama şüpheli olan kodu yakalar (kullanılmayan sonuç, yanlış `Printf` formatı gibi).

```
cd backend && go test ./...
```
Bütün testleri çalıştırır (`*_test.go` dosyaları: `cmd/loadgen` → `percentile`, `cmd/worker` → `decide`). Redis veya MySQL gerekmez. `-v` her testi ve alt testi ayrı satırda gösterir; `-count=1` önceki sonucu (`(cached)`) kullanmadan yeniden çalıştırır; `-run TestDecide` sadece adı eşleşeni çalıştırır.

```
gofmt -l .
```
Formatı bozuk dosyaları listeler. `-w` ile düzeltir: `gofmt -w .`

```
go mod tidy
```
`go.mod`'u temizler: kullanılmayan bağımlılıkları siler, eksikleri ekler.

### Veri üretme ve gönderme

```
cd backend && go run ./cmd/generate
```
200 sentetik event (timestamp'leri son 14 güne yayılmış) üretip `backend/events.json`'a yazar. Dosya varsa üzerine yazar.

```
cd backend && go run ./cmd/send
```
`events.json`'ı okur, her event'i `POST :8080/api/v1/events`'e gönderir. Her istek için status basar, sonunda gönderilen/başarısız özeti verir. **Go servisi ayakta olmalı.**

### Yük testi (`loadgen`)

```
cd backend && go run ./cmd/loadgen -rate 500 -duration 30s
```
Saniyede `-rate` kadar event'i `-duration` boyunca gönderir, sonunda status sayılarını ve latency p50 / p95 / p99 / max değerlerini basar. Default'lar 500/s ve 30s. `-url` ile hedef değişir (default `http://127.0.0.1:8080/api/v1/events`, Caddy'siz). **Go servisi ayakta olmalı.** Baseline ölçümü PLAN.md'de (Roadmap 1.1).

**Ölçerken API'nin çıktısı dosyaya gitsin, terminale değil.** Gin her istek için bir log satırı basar. Terminale yazmak yavaştır ve istekler bunu bekler: aynı kodda p99 terminalle 34 ms, dosyayla 5 ms çıktı. Karşılaştırılabilir sayı için API'yi şöyle başlat:

```
cd backend && go run ./cmd/api > /tmp/api.log 2>&1
```
`>` normal çıktıyı (stdout) dosyaya yönlendirir, `2>&1` hata çıktısını (stderr) da aynı yere. Log'u izlemek için başka bir terminalde: `tail -f /tmp/api.log`.

loadgen API'nin cevabını ölçer, yani event'in kuyruğa girişini. MySQL'e yazılması worker'ın işi: koşu bitince `XINFO GROUPS events`'teki `lag` 0'a inene kadar bekle, sonra say ya da sil.

Gönderilen event'lerin hepsinde `event_domain = 'loadgen.test'` var. Silinene kadar dashboard'daki toplamlara ve saatlik grafiğe girerler, o yüzden her koşudan sonra silinmeleri gerekir:

```
docker compose exec db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" events_db'
```
```sql
DELETE FROM events WHERE event_domain = 'loadgen.test';
```

---

## Laravel (`admin/`)

Hepsi `admin/` dizininden çalıştırılır.

```
composer install
```
PHP bağımlılıklarını `composer.json`'a göre kurar (`vendor/` klasörü).

```
php artisan serve
```
Laravel'i `:8000`'de başlatır.

```
php artisan migrate
```
Bekleyen migration'ları çalıştırır. **Sadece Laravel'in kendi tablolarını** yönetir (`users`, `sessions`, `cache`, `jobs`), hepsi `panel_db` içinde. `events` tablosu Go'nun, ona migration yazılmaz.

```
php artisan migrate:status
```
Hangi migration çalıştı, hangisi bekliyor gösterir.

```
php artisan route:list
```
Tanımlı tüm route'ları listeler. Filtrelemek için: `php artisan route:list --path=events`

```
php artisan tinker
```
Laravel'in tamamı yüklenmiş bir komut satırı açar: modelleri, config'i ve helper'ları doğrudan çağırabilirsin.

Tek seferlik sorgu için interaktif girmeden:

```
php artisan tinker --execute="echo App\Models\User::count();"
```

Panelde `events` için model yok, event verisi Go API'den geliyor. Event'leri saymak için `curl` ya da MySQL client kullan.

### Cache temizleme

Bir değişiklik yaptın ama tarayıcıda görünmüyorsa:

```
php artisan view:clear      # derlenmiş Blade template'leri
php artisan config:clear    # cache'lenmiş config
php artisan route:clear     # cache'lenmiş route'lar
php artisan optimize:clear  # hepsi birden
```

### Kod kontrolü

```
php -l app/Http/Controllers/EventController.php
```
Tek bir PHP dosyasının syntax kontrolü. Çalıştırmaz, sadece parse eder.

```
php artisan view:cache && php artisan view:clear
```
**Tüm** Blade dosyalarını derler — syntax hatası varsa burada patlar. `view:cache` production içindir, o yüzden hemen ardından `view:clear` ile geri al.

### Kod üretme

Laravel'in hazır iskelet dosyası üreten komutları. Dosyayı doğru klasöre, doğru namespace ile koyar:

```
php artisan make:controller UserController --resource
php artisan make:migration add_role_to_users_table
php artisan make:seeder ManagerSeeder
php artisan make:request StoreUserRequest
```

---

## Frontend (`admin/`)

```
npm install
```
JS bağımlılıklarını kurar (`node_modules/`).

```
npm run dev
```
Vite dev server'ı `:5173`'te başlatır ve `public/hot` dosyasını oluşturur. CSS/JS değiştirdiğinde tarayıcı otomatik güncellenir. **Panelde çalışırken açık kalmalı.**

```
npm run build
```
CSS ve JS'i `public/build/` altına derler. Dev server olmadan çalıştırmak için gerekli.

---

## Sorun giderme

**Panelde JS çalışmıyor / stil bozuk görünüyor**

```
ls admin/public/hot
```
Dosya yoksa Vite kapalı demektir. Laravel o zaman `public/build/` içindeki **eski derlenmiş** dosyaları servis eder. Çözüm: `npm run dev` (veya tek seferlik `npm run build`).

**Go bağlanamıyor: `connection refused`**

```
docker compose ps
```
`db` ve `redis` container'ları ayakta mı? Değilse `docker compose up -d`. Hata `redis at 127.0.0.1:6379: ...` diye başlıyorsa Redis, `dial tcp 127.0.0.1:3306` ise MySQL kapalı. API de worker da açılırken ikisine bağlanır; biri yoksa hemen durur.

**Mağaza `202` alıyor ama panelde yeni event yok**

Worker çalışmıyor ya da geride kalmış. Worker'ın terminaline bak (`cd backend && go run ./cmd/worker`). Kuyrukta bekleyen var mı:

```
docker compose exec redis redis-cli XINFO GROUPS events
```
`lag` bekleyen, `pending` alınmış ama henüz yazılmamış event sayısı. Worker açılınca ikisi de 0'a iner.

**Laravel `SQLSTATE[HY000] [1045] Access denied`**

Panel `panel` kullanıcısıyla bağlanıyor. `admin/.env` içindeki `DB_PASSWORD`, kök dizindeki `.env` içindeki `PANEL_DB_PASSWORD` ile aynı mı? `panel` kullanıcısı hiç oluşturulmadıysa yukarıdaki "Veritabanlarının kurulumu" adımlarını çalıştır.

**Market'te her sayfa "Not found" (404), ama `/admin` çalışıyor**

Proxy container'ı `market/` ve `proxy/` klasörlerini diskten bağlıyor. Git bu klasörleri silip yeniden oluşturursa (örneğin bu klasörlerin olmadığı bir branch'e geçip geri dönünce) container silinmiş eski klasörlere bağlı kalır ve içini boş görür. Container'ı yeniden oluştur:

```
docker compose up -d --force-recreate proxy
```

**Market'te yaptığım değişiklik görünmüyor / eski davranış devam ediyor**

Tarayıcı `shop.js` ya da `tracker.js`'in eski kopyasını kullanıyor olabilir. Caddy artık her market dosyasında tarayıcıya "önce bana sor" diyor (`Cache-Control: no-cache`), ama bu ayardan önce indirilmiş kopyalar için bir kez temizlemek gerekir:

1. Market sayfası açıkken DevTools'u aç (**Cmd + Option + I**).
2. Tarayıcının yenile (⟳) butonuna **sağ tıkla** → **"Empty Cache and Hard Reload"**.

Sepeti, session'ı ve demo girişi de sıfırlamak istersen: DevTools → **Application** → **Storage** → **Clear site data**.

**Panelde "The event service is not answering" (503)**

Panel event verisini Go API'den alıyor. Go servisi ayakta mı (`cd backend && go run ./cmd/api`)? `admin/.env` içindeki `EVENTS_API_KEY`, `backend/.env` içindekiyle aynı mı? Gerçek hata `admin/storage/logs/laravel.log` dosyasında.

---

## Postman

Go servisi ayaktayken `POST http://127.0.0.1:8080/api/v1/events`:

- **Body → raw → JSON** seçili olmalı.
- Zorunlu alanlar: `event_id` (UUID), `event_platform`, `event_domain`, `event_source`, `event_action`, `event_payload` (JSON object).
- Opsiyonel: `user_id`, `user_ip`, `event_timestamp` (RFC 3339, ör. `2026-09-20T14:30:00Z`). `event_timestamp` gönderilmezse API o anın saatini (UTC) koyar; gelecekteki bir zaman `400` alır.

Beklenen cevap: `202` + `{"event_id": "..."}` — kuyruğa alındı; satırı worker birkaç ms içinde yazar (worker kapalıysa açılınca). Aynı `event_id` ikinci kez gelirse yine `202`, ama tabloda tek satır olur. Eksik ya da bozuk alan `400`, Redis kapalıysa `503`.

---

## Git

```
git status                      # hangi dosyalar değişti
git diff                        # değişikliklerin içeriği
git diff --staged               # stage'lenmiş olanların içeriği
git add <dosya>                 # dosyayı stage'e al
git commit                      # editör açar, mesajı yaz
git push                        # remote'a gönder
git log --oneline -10           # son 10 commit
```

Her iş kendi branch'inde yapılır, onaylanınca `main`'e alınır (CLAUDE.md kuralı):

```
git switch -c redis-streams            # yeni branch aç ve geç
git push -u origin redis-streams       # branch'i remote'a gönder (ilk seferde -u)
git switch main                        # main'e dön
git merge --ff-only redis-streams      # birleştir; main geride kalmışsa reddeder
git push
```

`--ff-only`: `main` sadece ileri kaydırılır, ayrı bir merge commit'i oluşmaz. `main`'de branch'te olmayan bir commit varsa merge reddedilir ve hiçbir şey karışmaz.

Bir commit'i geri almak:

```
git revert <commit>             # o commit'in tersini yapan yeni bir commit ekler; push'lanmış commit'ler için güvenli yol
```
