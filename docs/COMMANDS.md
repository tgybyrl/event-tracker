# Sık kullanılan komutlar

Projede çalıştırdığımız komutlar. Yeni komut kullandıkça buraya eklenecek.

---

## Projeyi ayağa kaldırma

Üç terminal gerekiyor, bu sırayla:

```
docker compose up -d db          # 1. MySQL
cd backend && go run ./cmd/api   # 2. Go servisi   → :8080
cd admin && php artisan serve    # 3. Laravel      → :8000
```

Panelde geliştirme yapıyorsan dördüncü bir terminalde Vite de açık olmalı:

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
Container'ı **ve veriyi** siler. Tablo ve tüm satırlar gider, sıfırdan schema yüklemen gerekir.

### Şema yükleme

```
docker compose exec -T db mysql -u root -pSIFRE events_db < db/schema.sql
```
`db/schema.sql`'i container'daki MySQL'e gönderir. `-p` ile şifre arasında **boşluk yok**. Şifre shell history'sine düşer; tek seferlik kurulum komutu olduğu için kabul ediyoruz.

`-T` = TTY açma. Dosya yönlendirmesi (`<`) ile çalışması için gerekli.

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

---

## Go

```
cd backend && go run ./cmd/api
```
`backend/cmd/api/` içindeki `package main`'i derleyip çalıştırır. Servisi `:8080`'de ayağa kaldırır. `.env`'i çalışma dizininden okuduğu için **`backend/` içinden** çalıştırmak zorunlu.

```
go build ./...
```
Tüm paketleri derler ama çalıştırmaz. "Derleniyor mu?" kontrolü. `./...` = bu dizin ve altındaki her şey.

```
go vet ./...
```
Derlenen ama şüpheli olan kodu yakalar (kullanılmayan sonuç, yanlış `Printf` formatı gibi).

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
`events.json`'ı okur, her event'i `POST :8080/event`'e gönderir. Her istek için status basar, sonunda gönderilen/başarısız özeti verir. **Go servisi ayakta olmalı.**

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
Bekleyen migration'ları çalıştırır. **Sadece Laravel'in kendi tablolarını** yönetir (`users`, `sessions`, `cache`, `jobs`). `events` tablosu Go'nun, ona migration yazılmaz.

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
Laravel yüklenmiş bir REPL açar. Python'daki `python -i` gibi, ama tüm model ve config hazır gelir.

Tek seferlik sorgu için interaktif girmeden:

```
php artisan tinker --execute="echo App\Models\Event::count();"
```

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

### Kod üretme (phase D/E'de lazım olacak)

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
`db` container'ı ayakta mı? Değilse `docker compose up -d db`.

**Laravel `SQLSTATE[HY000] [1045] Access denied`**

`admin/.env` içindeki `DB_PASSWORD` ile kök dizindeki `.env` içindeki `MYSQL_ROOT_PASSWORD` aynı mı? Üç ayrı `.env` var ve elle senkron tutuluyor.

---

## Postman

Go servisi ayaktayken `POST http://127.0.0.1:8080/event`:

- **Body → raw → JSON** seçili olmalı.
- Zorunlu alanlar: `event_id`, `event_platform`, `event_domain`, `event_source`, `event_action`, `event_payload`.
- Opsiyonel: `user_id`, `user_ip`.
- `event_timestamp` gönderme — veritabanı `DEFAULT CURRENT_TIMESTAMP` ile kendisi dolduruyor.

Beklenen cevap: `201` + `{"event_id": "..."}`.

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

Phase başına branch (PLAN.md kuralı):

```
git switch -c panel-phase-c     # yeni branch aç ve geç
git switch main                 # main'e dön
git merge panel-phase-c         # phase bitince birleştir
git push
```
