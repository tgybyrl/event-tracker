# Sık kullanılan komutlar

Terminalde çalıştırdığımız/çalıştıracağımız komutlar, ne işe yaradıklarıyla
birlikte. Yeni komut eklendikçe buraya düşürülecek.

## Docker / MySQL

```
docker compose up -d db
```

`docker-compose.yaml`'daki `db` servisini (MySQL) arka planda başlatır.
`-d` = detached, terminali kilitlemez.

```
docker compose ps
```

Compose'un yönettiği container'ların (şu an sadece `db`) çalışıp
çalışmadığını gösterir.

```
docker compose exec db mysql -u root -p
```

Çalışan `db` container'ının **içine girip** MySQL client'ı başlatır.
`-u root` = root kullanıcısıyla bağlan, `-p` = şifre soracak
(`.env`'deki `MYSQL_ROOT_PASSWORD`).

MySQL client açıldıktan sonra:

```sql
USE events_db;          -- .env'deki MYSQL_DATABASE hangisiyse
SELECT * FROM events;   -- tablodaki satırları göster
```

## Go

```
cd backend && go run .
```

`backend/main.go`'yu (ve aynı paketteki diğer dosyaları) derleyip çalıştırır.
Servisi ayağa kaldırır (`config.ConnectDB()` + Gin router).

```
go build ./...
```

`backend` modülündeki **tüm** paketleri derler (çalıştırmaz, sadece
derlenebiliyor mu diye kontrol eder). `./...` = mevcut dizinden aşağı
tüm alt paketler.

```
go run ./tools/generate
```

`backend/tools/generate/main.go`'daki sentetik veri üretici script'ini
çalıştırır. `backend/events.json` dosyasını oluşturur/üzerine yazar.

## Postman

Servis ayaktayken (`go run .`), `POST http://127.0.0.1:8080/event` isteği:

- **Body → raw → JSON** seçili olmalı (Content-Type otomatik ayarlanır).
- Body'ye `event_id`, `event_platform`, `event_domain`, `event_source`,
  `event_action`, `event_payload` alanlarını içeren bir JSON obje yapıştır
  (`user_id`/`user_ip` opsiyonel).
