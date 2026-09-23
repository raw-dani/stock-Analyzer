# Deployment & Ops — Modul 15

Panduan operasional untuk menjalankan **US Stock Volume Analyzer** di produksi.

## 1. Database produksi (15.1)

Aplikasi support **SQLite** (dev), **MySQL**, dan **PostgreSQL**. Pemilihan driver
dilakukan via env `DB_DRIVER` di `config/db.php`. Semua migrasi bersifat
cross-driver karena foreign key ditambahkan via `ForeignKeyAwareTrait::addFkCompat()`
(skip di SQLite, aktif di MySQL/PostgreSQL).

```bash
# MySQL
export DB_DRIVER=mysql DB_HOST=127.0.0.1 DB_PORT=3306 \
       DB_NAME=stock_analyzer DB_USER=stock DB_PASSWORD='****'
# PostgreSQL
export DB_DRIVER=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_NAME=stock_analyzer ...
```

Lalu jalankan migrasi:
```bash
php yii migrate/up --interactive=0   # membuat semua tabel (11+)
```

Optimalisasi (untuk skala besar): indeks sudah ada di migrasi; `data/stocks.db`
hanya untuk dev.

## 2. Crontab (15.2)

Jadwal sesuai jam close pasar AS (16:00 ET ≈ 20:00–21:00 UTC). Lihat
`deploy/crontab.example`. Instal:

```bash
crontab deploy/crontab.example
```

## 3. Queue worker Redis (15.3)

Aplikasi memakai yii2-queue. Driver dipilih via `QUEUE_DRIVER`:
`sync` (dev), `redis` (produksi, butuh Redis + ekstensi phpredis), `file`.

```bash
export QUEUE_DRIVER=redis REDIS_HOST=... REDIS_PORT=6379
php yii queue/listen --verbose   # worker daemon
```

Job yang diproses: `FetchSymbolJob` (fetch data), `EvaluateAlertsJob` (alert).
Setiap `data/fetch` & `alert/dispatch` men-*push* job ke queue.

## 4. Docker (15.4)

```bash
cd deploy
cp .env.example .env   # lalu isi nilai
docker-compose up -d db redis
docker-compose run --rm app php yii migrate/up --interactive=0
docker-compose up -d app nginx
```

- `app` : PHP-FPM + cron + worker lewat supervisord.
- `nginx` : web server di port 8080.
- `db` / `redis` : MySQL & Redis.

## 5. Monitoring (15.5)

- **Health endpoint**: `GET /health` → JSON status DB/queue/provider. 503 bila DB down.
- **Log rotation**: `deploy/logrotate.conf` (+ Yii `maxLogFiles`).
- **Error alerting**: set env `ERROR_ALERT_EMAIL` untuk mengaktifkan `yii\log\EmailTarget`
  yang mengirim notifikasi saat error/warning.
- **Logs**: `runtime/logs/app.log` (error/warning), `runtime/logs/services.log` (info service).

## Env ringkas

| Var | Default | Fungsi |
|---|---|---|
| `DB_DRIVER` | `sqlite` | `mysql`/`pgsql`/`sqlite` |
| `DB_HOST`/`DB_PORT`/`DB_NAME`/`DB_USER`/`DB_PASSWORD` | — | kredensial DB |
| `QUEUE_DRIVER` | `sync` | `redis`/`file`/`sync` |
| `REDIS_HOST`/`REDIS_PORT` | `127.0.0.1`/`6379` | Redis (bila queue redis) |
| `ERROR_ALERT_EMAIL` | — | aktifkan email alerting |
| `ALPHAVANTAGE_API_KEY` / `POLYGON_API_KEY` | — | API key provider |