# Panduan Khusus Deployment di CyberPanel — stock.pemain12.com

Dokumen ini menjelaskan langkah demi langkah instalasi dan konfigurasi aplikasi **US Stock Volume & Strategy Analyzer (Yii2 Framework)** pada **CyberPanel (OpenLiteSpeed)** menggunakan domain **`stock.pemain12.com`**.

---

## 1. Prasyarat Server & Kebutuhan Sistem

- **Panel:** CyberPanel v2.x (OpenLiteSpeed)
- **PHP:** PHP 8.1 atau 8.2 (disarankan `ea-php81` atau `lsphp81` / `lsphp82`)
- **Ekstensi PHP Wajib:**
  - `pdo`, `pdo_mysql`, `pdo_sqlite`
  - `curl`, `mbstring`, `intl`, `json`, `xml`, `zip`, `fileinfo`
- **Database:** MySQL / MariaDB (atau SQLite)
- **Composer:** v2.x terpasang secara global di server
- **DNS Domain:** A-Record `stock.pemain12.com` telah diarahkan ke IP VPS / Server CyberPanel Anda.

---

## 2. Membuat Website & Database di CyberPanel

### A. Buat Website Baru
1. Login ke Dashboard CyberPanel (`https://IP_SERVER:8090`).
2. Masuk ke menu **Websites** &rarr; **Create Website**.
3. Isi parameter berikut:
   - **Select Package:** `Default` (atau paket hosting Anda)
   - **Select Owner:** `admin` (atau user yang Anda inginkan)
   - **Domain Name:** `stock.pemain12.com`
   - **Email:** Alamat email Anda
   - **Select PHP:** `PHP 8.1` atau `PHP 8.2`
   - **Additional Features:** Centang `SSL`, `DKIM Support`, `open_basedir Protection`.
4. Klik **Create Website**.

### B. Buat Database MySQL
1. Masuk ke menu **Databases** &rarr; **Create Database**.
2. Pilih Domain: `stock.pemain12.com`.
3. Isi detail database:
   - **Database Name:** cth: `stock_analyzer` (nama lengkap: `stockpemain12_stock_analyzer`)
   - **Username:** cth: `stock_user`
   - **Password:** Masukkan password yang kuat dan catat baik-baik.
4. Klik **Create Database**.

---

## 3. Upload File Source Code Proyek

Lokasi root website di CyberPanel secara default berada di:
```text
/home/stock.pemain12.com/public_html
```

Anda bisa mengupload kode melalui **Git Clone (disarankan)** atau **File Manager (Zip)**:

### Opsi A: Menggunakan Git / SSH (Direkomendasikan)
Masuk via terminal SSH ke server Anda:
```bash
# Pindah ke direktori home website
cd /home/stock.pemain12.com

# Cadangkan public_html bawaan
mv public_html public_html_bak

# Clone repositori Anda ke direktori public_html
git clone <URL_REPO_ANDA> public_html

# Masuk ke folder proyek
cd /home/stock.pemain12.com/public_html
```

### Opsi B: Menggunakan File Manager CyberPanel
1. Masuk ke **Websites** &rarr; **List Websites** &rarr; Klik **File Manager** pada `stock.pemain12.com`.
2. Hapus file `index.html` default di dalam folder `public_html`.
3. Upload file zip proyek, lalu **Extract** langsung ke dalam `/home/stock.pemain12.com/public_html`.

---

## 4. Konfigurasi Document Root ke Folder `/web` (PENTING)

> [!IMPORTANT]
> Yii2 menggunakan folder `web/` sebagai entry point (`index.php`). Demi keamanan dan struktur Yii2 yang benar, Document Root OpenLiteSpeed harus diarahkan ke `/web`.

1. Di CyberPanel, masuk ke **Websites** &rarr; **List Websites**.
2. Klik **Manage** pada domain `stock.pemain12.com`.
3. Klik tombol **vHost Conf** (Virtual Host Configuration).
4. Cari bagian `docRoot` dan ubah menjadi:
   ```text
   docRoot                   $VH_ROOT/public_html/web
   ```
5. Simpan konfigurasi (**Save**). OpenLiteSpeed akan restart otomatis.

---

## 5. Install Dependency & Setup Lingkungan (.env)

Masuk via SSH terminal ke direktori proyek:
```bash
cd /home/stock.pemain12.com/public_html
```

### A. Jalankan Composer Install
```bash
# Gunakan binary PHP LiteSpeed sesuai versi yang dipilih (misal PHP 8.1)
/usr/local/lsws/lsphp81/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader
```
*(Atau jika `composer` dan `php` CLI sudah terhubung global, cukup jalankan `composer install --no-dev -o`)*.

### B. Konfigurasi File `.env`
Salin template atau buat file `.env` baru:
```bash
nano .env
```
Isi konfigurasi database MySQL Anda:
```env
# Mode Produksi
YII_DEBUG=false
YII_ENV=prod

# Database Konfigurasi (MySQL)
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=stockpemain12_stock_analyzer
DB_USER=stockpemain12_stock_user
DB_PASSWORD=PASSWORD_DATABASE_ANDA
DB_SCHEMA_CACHE=1

# Antrean Background (Queue)
QUEUE_DRIVER=db
```
Simpan file (`Ctrl + O`, `Enter`, lalu `Ctrl + X`).

---

## 6. Jalankan Migrasi Database & Seeding Akun

Jalankan perintah Yii CLI untuk membuat struktur tabel dan membuat akun administrator awal:

```bash
# 1. Jalankan Migrasi Database
/usr/local/lsws/lsphp81/bin/php yii migrate --interactive=0

# 2. Seed Data Akun Admin Awal
/usr/local/lsws/lsphp81/bin/php scripts/seed-admin.php

# 3. (Opsional) Sinkronisasi Saham Awal
/usr/local/lsws/lsphp81/bin/php yii data/sync-all
```

> [!NOTE]
> Akun bawaan admin setelah `seed-admin.php`:
> - **Username:** `admin`
> - **Password:** `Admin123!` (Segera ubah setelah login pertama kali)

---

## 7. Permission & Hak Akses Direktori (Chown & Chmod)

OpenLiteSpeed di CyberPanel berjalan di bawah user dan grup pengguna website (`stock.pemain12.com`). Pastikan direktori `runtime/`, `web/assets/`, dan `data/` memiliki izin tulis (writeable):

```bash
cd /home/stock.pemain12.com/public_html

# Ubah kepemilikan ke user website
chown -R stock.pemain12.com:stock.pemain12.com runtime web/assets data

# Berikan hak akses tulis
chmod -R 775 runtime web/assets data
```

---

## 8. Konfigurasi OpenLiteSpeed Rewrite Rules (.htaccess)

Agar routing Pretty URL Yii2 berjalan sempurna tanpa muncul pesan error 404 pada menu/link:

1. Di CyberPanel, buka **Websites** &rarr; **List Websites** &rarr; Klik **Manage** pada `stock.pemain12.com`.
2. Klik tombol **Rewrite Rules**.
3. Pastikan terdapat aturan rewrite Yii2 berikut:
   ```apache
   RewriteEngine On
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteCond %{REQUEST_FILENAME} !-d
   RewriteRule . index.php [L]
   ```
4. Klik **Save Rewrite Rules**.

---

## 9. Konfigurasi SSL (HTTPS) Gratis Let's Encrypt

1. Masuk ke menu **SSL** &rarr; **Manage SSL** di CyberPanel.
2. Pilih domain: `stock.pemain12.com`.
3. Klik **Issue SSL**.
4. Tunggu hingga status *Success* muncul. Website kini sudah dapat diakses aman via `https://stock.pemain12.com`.

---

## 10. Konfigurasi Otomatisasi (Cron Job) di CyberPanel

Aplikasi ini memiliki modul sinkronisasi harga pasar saham Yahoo Finance dan kalkulasi indikator volume / sinyal. Buat Cron Job di CyberPanel:

1. Buka menu **Websites** &rarr; **List Websites** &rarr; **Manage** (`stock.pemain12.com`).
2. Pilih menu **Cron Jobs** &rarr; **Add Cron Job**.
3. Tambahkan beberapa task berikut:

| Nama Task | Waktu / Schedule | Command |
|---|---|---|
| **Sync Data Pasar Saham & EOD** | Setiap Hari Kerja (02:00 WIB) `0 2 * * 2-6` | `/usr/local/lsws/lsphp81/bin/php /home/stock.pemain12.com/public_html/yii data/sync-all > /dev/null 2>&1` |
| **Sync Data Candlestick & Volume Crypto** | Setiap 10 Menit `*/10 * * * *` | `/usr/local/lsws/lsphp81/bin/php /home/stock.pemain12.com/public_html/yii crypto/sync --limit=60 > /dev/null 2>&1` |
| **Worker Queue (Background)** | Tiap Menit `* * * * *` | `/usr/local/lsws/lsphp81/bin/php /home/stock.pemain12.com/public_html/yii queue/run > /dev/null 2>&1` |

---

## 11. Troubleshooting: Mengatasi Database Exception (#42S02)

Jika saat membuka menu (misal menu `/crypto`), muncul error:
> **Database Exception (#42S02)**  
> *SQLSTATE[42S02]: Base table or view not found*

Penyebabnya adalah skema tabel modul belum dibuat di database MySQL CyberPanel atau masalah case-sensitivity pada Linux.

### Solusi 1: Jalankan Migrasi Database via SSH (Direkomendasikan)
```bash
cd /home/stock.pemain12.com/public_html
php yii migrate
```
Ketik `yes` saat konfirmasi untuk membuat tabel yang kurang (`crypto_asset`, `crypto_price`, `crypto_analysis`, dll).

### Solusi 2: Buat Tabel via phpMyAdmin CyberPanel
Jika tidak memiliki akses terminal SSH:
1. Buka dashboard CyberPanel &rarr; **Databases** &rarr; **phpMyAdmin**.
2. Pilih database website Anda.
3. Buka tab **SQL**, paste query pembuatan tabel (misal tabel modul crypto), lalu klik **Go**.

### Solusi 3: Case-Sensitivity Table Names di Linux MySQL
Linux MariaDB/MySQL bersifat case-sensitive. Jika nama tabel berbeda huruf kapital:
1. Edit file `/etc/my.cnf` melalui SSH:
   ```ini
   [mysqld]
   lower_case_table_names = 1
   ```
2. Restart MariaDB di CyberPanel (**Manage Services** &rarr; **MariaDB** &rarr; **Restart**).

---

## 12. Optimasi Load Time & Kecepatan Server (Mengatasi Halaman Lemot >20s)

Jika halaman web terasa lambat atau waktu tunggu (TTFB / Request time) tinggi saat dibuka di CyberPanel:

### A. Nonaktifkan Debug Mode di Production
Pastikan mode debug dinonaktifkan di `web/index.php`. Saat `YII_DEBUG` aktif, Yii2 mencatat ratusan kueri ke disk log setiap request:
```php
defined('YII_DEBUG') or define('YII_DEBUG', false);
defined('YII_ENV') or define('YII_ENV', 'prod');
```

### B. Aktifkan PHP OPcache di CyberPanel
OPcache menyimpan bytecode PHP di memori RAM sehingga tidak perlu kompilasi ulang setiap request:
1. Buka CyberPanel &rarr; **PHP** &rarr; **Edit PHP Configs** &rarr; tab **Advanced**.
2. Pilih versi PHP aktif (cth: `PHP 8.1` / `lsphp81`).
3. Pastikan konfigurasi berikut aktif:
   ```ini
   opcache.enable=1
   opcache.memory_consumption=256
   opcache.interned_strings_buffer=16
   opcache.max_accelerated_files=10000
   ```
4. Restart service PHP via menu **Manage Services** &rarr; **PHP**.

### C. Hindari Fetch API Eksternal Sinkron di Tengah Request Web
- Jangan memanggil API eksternal (seperti Binance API atau Yahoo Finance) satu per satu secara sinkron di dalam loop view/controller saat pengguna membuka halaman.
- Gunakan preloading database satu kueri (`IN (...)`) untuk menghindari masalah N+1 query.
- Pindahkan proses download data berat ke Cron Job berkala (`php yii crypto/sync`) atau eksekusi via background AJAX (`/crypto/sync-all`).

---

## 13. Checklist Verifikasi Akhir

- [ ] Buka `https://stock.pemain12.com` di browser &mdash; halaman Dashboard / Login tampil sempurna.
- [ ] Login menggunakan akun admin (`admin` / `Admin123!`).
- [ ] Cek halaman **Portofolio** (`/portfolio`), **Stock Screener** (`/stock`), dan **Strategi Confluence** (`/strategy-confluence`).
- [ ] Cek halaman **Crypto Scanner & Hub** (`/crypto`), **Bullish Patterns** (`/crypto/bullish-patterns`), dan **Crypto Confluence** (`/crypto/confluence`) &mdash; pastikan load cepat tanpa error 42S02.
- [ ] Tes fitur **Form Edit Posisi** dan pastikan modal dapat dibuka dan disimpan tanpa kendala.
- [ ] Cek endpoint REST API:
  ```bash
  curl -I https://stock.pemain12.com/api/v1/stocks
  ```
  *(Pastikan mengembalikan HTTP status 200 OK)*.
