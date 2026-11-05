# Setup, Deploy & Backup

Panduan menjalankan Dental PMS di laptop developer, men-deploy ke VPS produksi, dan memulihkan backup.

---

## 1. Kebutuhan

| Komponen | Versi |
|---|---|
| PHP | 8.3, 8.4, atau 8.5 (Ubuntu 26.04 memakai 8.5; semuanya diuji di CI) dengan ekstensi `intl`, `zip`, `pdo_mysql`, `redis`, `mbstring`, `gd` |
| Composer | 2.x |
| Database | **MySQL 8.4 LTS** (dev boleh 8.0). Klien `mysqldump` wajib ada untuk backup. |
| Redis | 6+ (queue, cache) |
| Web server | Nginx + PHP-FPM, **HTTPS wajib** (PWA & MFA) |
| Node.js | Tidak wajib (panel Filament tidak perlu build aset) |

---

## 2. Setup Lokal

```bash
git clone https://github.com/felicidad126/web-app-rz.git dental-pms
```

```bash
cd dental-pms && composer install
```

```bash
cp .env.example .env && php artisan key:generate
```

Isi `.env`:
- `DB_*` sesuai MySQL lokal.
- `BLIND_INDEX_KEY` (lihat §4).
- `BACKUP_ARCHIVE_PASSWORD`.

Lalu:

```bash
php artisan migrate --seed
```

```bash
php artisan db:seed --class=DemoSeeder
```

```bash
php artisan serve
```

Buka http://localhost:8000.
- **Akun demo:** `owner@demo.test`, `admin@demo.test`, `dokter@demo.test`, `perawat@demo.test`, `resepsionis@demo.test`, `kasir@demo.test`. Semua berpassword `password`, dan MFA diatur saat login pertama.
- **Import CSV:** jalankan worker di terminal lain:

```bash
php artisan queue:work
```

### Pemeriksaan sebelum commit

```bash
composer check
```

(`pint --test` + `phpstan` + `pest`.) Test default memakai SQLite in-memory. Untuk menjalankannya di MySQL:

```bash
DB_CONNECTION=mysql DB_DATABASE=dental_pms_test DB_USERNAME=dental DB_PASSWORD=secret vendor/bin/pest
```

---

## 3. Deploy Produksi (VPS)

### 3.0 Cara cepat: script instalasi (Ubuntu 26.04 / 24.04)

`deploy/provision.sh` mengerjakan semua langkah 3.1–3.5 sekaligus:
- memasang PHP-FPM, MySQL, Redis, Nginx, Supervisor, cron, dan Let's Encrypt
- meng-clone aplikasi
- membuat database & kunci enkripsi
- membuat akun owner
- menjalankan uji backup

Script sudah diuji end-to-end di Ubuntu 26.04 (PHP 8.5, MySQL 8.4, Nginx 1.28) dan aman dijalankan ulang: kunci dan password yang sudah ada tidak diganti.

Di VM, sebagai user dengan akses sudo:

```bash
curl -fsSLO https://raw.githubusercontent.com/felicidad126/web-app-rz/claude/stoic-sagan-qer84s/deploy/provision.sh
```

```bash
sudo bash provision.sh
```

Script akan menanyakan:
1. **Domain.** Isi jika sudah ada domain yang A record-nya mengarah ke server; HTTPS dari Let's Encrypt dipasang otomatis. Kosongkan jika belum ada.
2. **HTTPS lewat Tailscale** (y/n). Hanya ditanyakan jika domain kosong dan Tailscale di server sudah siap (lihat §3.0.1).
3. **Data demo** (y/n). Jika `y`, `APP_ENV=staging` dan akun demo dibuat dengan password acak.
4. **Firewall UFW** (y/n). Port SSH yang sedang dipakai dideteksi otomatis dan tetap dibuka.
5. **Akun owner** (nama, email, password).

Tanpa domain dan tanpa Tailscale, aplikasi diakses via `http://IP` tanpa HTTPS. Fitur PWA (pasang aplikasi, offline) tidak aktif tanpa HTTPS.

Hasilnya:
- Aplikasi di `/var/www/dental-pms`.
- Kredensial penting (APP_KEY, BLIND_INDEX_KEY, password backup & database, password demo) di `/root/dental-pms-credentials.txt`. **Pindahkan ke password manager, lalu hapus file itu.**

Update kode dan setelah mengubah `.env`:

```bash
sudo bash /var/www/dental-pms/deploy/update.sh
```

#### 3.0.1 HTTPS lewat Tailscale (tanpa domain)

Cocok untuk staging atau klinik yang belum punya domain. Aplikasi hanya bisa dibuka dari perangkat yang login ke tailnet yang sama, dan tidak terbuka ke internet.

Siapkan sekali:
1. Di admin console Tailscale, menu **DNS**: aktifkan **MagicDNS** dan **HTTPS Certificates**.
2. Di server: Tailscale 1.52 atau lebih baru, sudah login (`sudo tailscale up`).

Lalu jalankan `sudo bash provision.sh`, kosongkan domain, dan jawab `y` saat ditanya HTTPS lewat Tailscale. Mode non-interaktif: `TAILSCALE_HTTPS=y`.

Yang dilakukan script:
- `tailscale serve --bg http://127.0.0.1:80`: Tailscale menerima `https://<nama-vm>.<tailnet>.ts.net` (sertifikat diurus Tailscale) lalu meneruskan ke Nginx.
- Nginx aplikasi hanya mendengarkan `127.0.0.1:80`. Akses `http://IP` dialihkan ke alamat `.ts.net`.
- `.env`: `APP_URL=https://<nama>.ts.net`, `TRUSTED_PROXIES` ditambah `127.0.0.1,::1`, `SESSION_SECURE_COOKIE=true`.

Jika Tailscale belum siap, script memberi peringatan dan melanjutkan dengan akses via IP. Jalankan ulang script setelah diperbaiki; kunci dan data tidak berubah.

Langkah 3.1–3.5 di bawah adalah rincian manual dari apa yang dilakukan script.

### 3.1 Pertama kali

```bash
composer install --no-dev --optimize-autoloader
```

```bash
cp .env.example .env && php artisan key:generate
```

Isi `.env` produksi (lihat §4), lalu:

```bash
php artisan migrate --force && php artisan db:seed --force
```

```bash
php artisan clinic:create-owner
```

```bash
php artisan storage:link && php artisan optimize && php artisan filament:optimize
```

Untuk akun IT/developer dengan akses penuh, buat user lalu jalankan:

```bash
php artisan shield:super-admin --user=<ID_USER> --panel=admin
```

### 3.2 Setiap update

```bash
git pull && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan db:seed --class=RolePermissionSeeder --force && php artisan optimize && php artisan filament:optimize && php artisan queue:restart
```

### 3.3 Queue worker (Supervisor)

`/etc/supervisor/conf.d/dental-pms-worker.conf`:

```ini
[program:dental-pms-worker]
command=php /var/www/dental-pms/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
user=www-data
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/dental-pms/storage/logs/worker.log
```

### 3.4 Scheduler (cron)

```bash
echo '* * * * * www-data cd /var/www/dental-pms && php artisan schedule:run >> /dev/null 2>&1' | sudo tee /etc/cron.d/dental-pms
```

Jadwal yang aktif (WIB):

| Jam | Tugas |
|---|---|
| 01:00 | `backup:clean` |
| 01:30 | `backup:run` |
| 07:00 | `backup:monitor` (email jika backup terakhir > 1 hari atau terlalu besar) |
| Senin 07:30 | `clinic:check-sip-expiry` |
| 00:00 | Bersihkan job batch & failed job lama |

Cek dengan `php artisan schedule:list`.

### 3.5 Nginx (ringkas)

```nginx
server {
    listen 443 ssl http2;
    server_name klinik.example.com;
    root /var/www/dental-pms/public;

    ssl_certificate     /etc/letsencrypt/live/klinik.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/klinik.example.com/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    index index.php;
    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Service worker harus selalu versi terbaru.
    location = /sw.js {
        add_header Cache-Control "no-cache";
        try_files $uri =404;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;  # sesuaikan versi PHP
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 4. Variabel `.env` Penting

| Variabel | Keterangan |
|---|---|
| `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...` | Wajib di produksi |
| `APP_KEY` | Kunci enkripsi (NIK, secret MFA, session). **Simpan cadangannya di password manager owner.** Kalau hilang, NIK dan MFA tidak bisa didekripsi. |
| `BLIND_INDEX_KEY` | Kunci HMAC untuk pencarian NIK. Generate sekali dengan `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`. **Jangan pernah diganti setelah ada data.** Simpan cadangannya bersama `APP_KEY`. |
| `TRUSTED_PROXIES` | Isi hanya jika aplikasi di balik reverse proxy, Cloudflare, atau load balancer (IP dipisah koma, atau `*`), supaya URL aset dan cookie memakai HTTPS. Kosongkan untuk Nginx + PHP-FPM langsung. Mode Tailscale: diisi otomatis `127.0.0.1,::1`. Jika `APP_URL` diawali `https://`, link dan redirect selalu dibuat https. |
| `SESSION_LIFETIME=30`, `SESSION_ENCRYPT=true` | Idle timeout 30 menit |
| `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis` | Cache juga dipakai Filament untuk anti-replay kode MFA |
| `MAIL_*` | Untuk MFA via email, reset password, dan notifikasi backup gagal |
| `CLINIC_RM_PREFIX`, `CLINIC_RM_PAD` | Format nomor RM, mis. prefix `RM-` + 6 digit → `RM-000001` |
| `BACKUP_DISK` | `backup` (S3) di produksi. Bisa beberapa disk dipisah koma: `backup,local`. |
| `BACKUP_ARCHIVE_PASSWORD` | Password enkripsi arsip (AES-256). **Wajib.** Simpan di password manager. |
| `BACKUP_AWS_*` | Kredensial bucket S3-compatible (AWS Jakarta `ap-southeast-3` atau penyedia lokal yang kompatibel S3; isi `BACKUP_AWS_ENDPOINT` untuk non-AWS) |
| `BACKUP_NOTIFICATION_EMAIL` | Penerima email jika backup gagal/tidak sehat |

> ⚠️ Tanpa `APP_KEY`, `BLIND_INDEX_KEY`, dan `BACKUP_ARCHIVE_PASSWORD`, backup database **tidak bisa dipakai**. Simpan ketiganya di luar server.

---

## 5. Backup & Restore

### 5.1 Isi backup

Satu file ZIP terenkripsi AES-256 per hari, berisi:
- `db-dumps/mysql-<database>.sql`: dump seluruh database.
- `storage/app/private/**` dan `storage/app/public/**`: file klinis (rontgen, consent, PDF; mulai Fase 2).

Kode aplikasi tidak ikut di-backup karena sudah ada di Git.

Retensi: semua backup 7 hari terakhir, lalu 1 per minggu selama 4 minggu, 1 per bulan selama 12 bulan, dan 1 per tahun selama 5 tahun.

### 5.2 Backup manual

```bash
php artisan backup:run
```

```bash
php artisan backup:list
```

### 5.3 Restore (sudah diuji)

1. Unduh ZIP dari bucket (folder `dental-pms/`, nama file = tanggal-jam backup) ke server pemulihan.
2. Ekstrak dengan password `BACKUP_ARCHIVE_PASSWORD`. ZIP memakai AES-256, jadi pakai `7z` (bukan `unzip` bawaan, yang tidak mendukung AES):

```bash
7z x 2026-11-04-01-30-00.zip -o/tmp/restore
```

3. Buat database kosong, lalu import dump:

```bash
mysql -e "CREATE DATABASE dental_pms_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

```bash
mysql dental_pms_restore < /tmp/restore/db-dumps/mysql-dental_pms.sql
```

4. Salin `storage/app/private` dan `storage/app/public` dari folder ekstrak ke aplikasi.
5. Pastikan `.env` aplikasi memakai **`APP_KEY` dan `BLIND_INDEX_KEY` yang sama** dengan saat backup dibuat. Tanpa itu, NIK dan MFA tidak bisa dibaca.
6. Cocokkan jumlah baris tabel utama dengan database lama (lihat skrip di bawah), lalu arahkan aplikasi ke database hasil restore.

```bash
for t in patients users treatments items activity_log; do echo "$t: $(mysql -N -e "select count(*) from dental_pms_restore.$t")"; done
```

**Uji restore minimal sebulan sekali** (Planning v2 §4.2 no. 8), ke database terpisah, lalu catat hasilnya.

---

## 6. Keamanan Operasional

- Semua staf wajib MFA. Jika HP hilang, Owner/Admin memakai **Reset MFA** di menu *Pengguna* (aksi ini tercatat di audit log).
- Nonaktifkan (jangan hapus) akun staf yang keluar.
- *Audit Log* (Owner/Admin) mencatat perubahan data dan **akses** ke data pasien (`log: access`).
- Jangan jalankan `activitylog:clean`, karena audit log rekam medis harus disimpan jangka panjang.
- `DemoSeeder` menolak berjalan di `APP_ENV=production`.
