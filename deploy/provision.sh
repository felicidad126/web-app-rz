#!/usr/bin/env bash
#
# Dental PMS — instalasi server (Ubuntu 26.04 LTS, juga jalan di 24.04).
#
# Memasang PHP-FPM, MySQL, Redis, Nginx, Supervisor (queue worker), cron (scheduler),
# Let's Encrypt (jika ada domain), lalu meng-clone & menyiapkan aplikasi.
#
# Pemakaian (sebagai root):
#   sudo bash provision.sh
#
# Aman dijalankan ulang: kunci enkripsi & password database yang sudah ada TIDAK diganti.
#
# Variabel opsional (untuk mode non-interaktif):
#   APP_DOMAIN=staging.klinik.com  LE_EMAIL=admin@klinik.com  DEMO_DATA=y|n
#   TAILSCALE_HTTPS=y|n   (tanpa domain: HTTPS via https://<nama-vm>.<tailnet>.ts.net)
#   REPO_URL=...  REPO_BRANCH=...  APP_DIR=/var/www/dental-pms  ENABLE_UFW=y|n
#   OWNER_NAME=...  OWNER_EMAIL=...  OWNER_PASSWORD=...
#
set -Eeuo pipefail

REPO_URL="${REPO_URL:-https://github.com/felicidad126/web-app-rz.git}"
REPO_BRANCH="${REPO_BRANCH:-claude/stoic-sagan-qer84s}"
APP_DIR="${APP_DIR:-/var/www/dental-pms}"
APP_USER="www-data"
DB_NAME="dental_pms"
DB_USER="dental"
CREDENTIALS_FILE="/root/dental-pms-credentials.txt"
export DEBIAN_FRONTEND=noninteractive

# ---------------------------------------------------------------------------
# Helper
# ---------------------------------------------------------------------------
c_info=$'\e[1;36m'; c_ok=$'\e[1;32m'; c_warn=$'\e[1;33m'; c_err=$'\e[1;31m'; c_off=$'\e[0m'
step() { echo; echo "${c_info}==> $*${c_off}"; }
ok() { echo "${c_ok}    ✓ $*${c_off}"; }
warn() { echo "${c_warn}    ! $*${c_off}"; }
die() { echo "${c_err}ERROR: $*${c_off}" >&2; exit 1; }
trap 'die "Gagal di baris $LINENO: $BASH_COMMAND"' ERR

is_tty() { [[ -t 0 && -t 1 ]]; }

ask() { # ask VAR "Pertanyaan" "default"
    local var="$1" prompt="$2" default="${3:-}" answer
    if [[ -n "${!var:-}" ]]; then return; fi
    if is_tty; then
        read -r -p "    $prompt${default:+ [$default]}: " answer
        printf -v "$var" '%s' "${answer:-$default}"
    else
        printf -v "$var" '%s' "$default"
    fi
}

has_systemd() { [[ -d /run/systemd/system ]]; }

svc() { # svc restart|reload|enable nama
    local action="$1" name="$2"
    if has_systemd; then
        if [[ "$action" == "enable" ]]; then systemctl enable --now "$name" >/dev/null 2>&1; else systemctl "$action" "$name"; fi
        return
    fi
    # Tanpa systemd (container/WSL): pakai init script jika ada.
    if [[ -x "/etc/init.d/$name" ]]; then
        if [[ "$action" == "enable" ]]; then
            service "$name" status >/dev/null 2>&1 || service "$name" start >/dev/null 2>&1 || true
        else
            service "$name" "$action" >/dev/null 2>&1 || true
            sleep 1
            service "$name" status >/dev/null 2>&1 || service "$name" start >/dev/null 2>&1 || true
        fi
    fi
    if [[ "$name" == "redis-server" ]] && ! redis-cli ping >/dev/null 2>&1; then
        redis-server /etc/redis/redis.conf --daemonize yes >/dev/null 2>&1 || true
    elif [[ "$name" == "mysql" ]] && ! mysqladmin ping --silent >/dev/null 2>&1; then
        # MySQL 8.4 di Ubuntu 26.04 hanya punya unit systemd.
        install -d -o mysql -g mysql /run/mysqld
        runuser -u mysql -- /usr/sbin/mysqld --daemonize --pid-file=/run/mysqld/mysqld.pid >/dev/null 2>&1 || true
    fi
}

wait_for_mysql() {
    local _
    for _ in $(seq 1 60); do
        mysqladmin ping --silent >/dev/null 2>&1 && return 0
        sleep 1
    done
    die "MySQL tidak bisa dijalankan. Cek: journalctl -u mysql  atau  /var/log/mysql/error.log"
}

wait_for_redis() {
    local _
    for _ in $(seq 1 30); do
        [[ "$(redis-cli ping 2>/dev/null)" == "PONG" ]] && return 0
        sleep 1
    done
    die "Redis tidak bisa dijalankan. Cek: journalctl -u redis-server"
}

as_app() { runuser -u "$APP_USER" -- env HOME=/var/www COMPOSER_HOME=/var/www/.composer "$@"; }
artisan() { (cd "$APP_DIR" && as_app php artisan "$@"); }

env_get() { { grep -E "^$1=" "$APP_DIR/.env" 2>/dev/null || true; } | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
env_set() { # env_set KEY VALUE  (ganti jika ada, tambah jika belum)
    local key="$1" value="$2" file="$APP_DIR/.env"
    local escaped
    escaped=$(printf '%s' "$value" | sed -e 's/[\/&|]/\\&/g')
    if grep -qE "^${key}=" "$file"; then
        sed -i "s|^${key}=.*|${key}=${escaped}|" "$file"
    else
        printf '%s=%s\n' "$key" "$value" >> "$file"
    fi
    chown "$APP_USER:$APP_USER" "$file"
    chmod 640 "$file"
}

random_secret() { openssl rand -hex "$(( ${1:-32} / 2 ))"; }

# Nama *.ts.net mesin ini yang boleh diberi sertifikat HTTPS oleh Tailscale.
# Kosong jika Tailscale tidak terpasang, belum login, atau "HTTPS Certificates" belum aktif.
ts_cert_domain() {
    command -v tailscale >/dev/null 2>&1 || return 0
    command -v python3 >/dev/null 2>&1 || return 0
    tailscale status --json 2>/dev/null | python3 -c '
import json, sys
try:
    d = json.load(sys.stdin)
except ValueError:
    sys.exit(0)
if d.get("BackendState") == "Running":
    print((d.get("CertDomains") or [""])[0])
' 2>/dev/null || true
}

add_trusted_proxies() { # tambah IP ke TRUSTED_PROXIES tanpa menghapus yang sudah ada
    local current ip
    current=$(env_get TRUSTED_PROXIES)
    [[ "$current" == "*" ]] && return 0
    for ip in "$@"; do
        [[ ",${current}," == *",${ip},"* ]] || current="${current:+${current},}${ip}"
    done
    env_set TRUSTED_PROXIES "$current"
}

# ---------------------------------------------------------------------------
# 0. Pemeriksaan awal & input
# ---------------------------------------------------------------------------
[[ $EUID -eq 0 ]] || die "Jalankan sebagai root: sudo bash $0"
# shellcheck disable=SC1091
. /etc/os-release
[[ "${ID:-}" == "ubuntu" ]] || die "Script ini untuk Ubuntu (terdeteksi: ${PRETTY_NAME:-?})."
case "${VERSION_ID:-}" in
    26.04|24.04) ;;
    *) warn "Diuji untuk Ubuntu 26.04/24.04, terdeteksi ${VERSION_ID:-?}. Lanjut dengan risiko sendiri." ;;
esac

echo "${c_info}Dental PMS — instalasi server${c_off}"
echo "    Repo   : $REPO_URL ($REPO_BRANCH)"
echo "    Folder : $APP_DIR"
echo

if ! is_tty && [[ -z "${APP_DOMAIN+x}" ]]; then
    warn "Tidak ada terminal interaktif: memakai nilai default (tanpa domain, HTTPS Tailscale bila siap, data demo, UFW aktif)."
    warn "Disarankan: unduh script lalu jalankan 'sudo bash provision.sh' agar bisa menjawab pertanyaan."
fi
ask APP_DOMAIN "Domain untuk aplikasi (kosongkan jika belum ada: akses via Tailscale atau IP)" ""
if [[ -n "$APP_DOMAIN" ]]; then
    ask LE_EMAIL "Email untuk sertifikat Let's Encrypt" ""
    [[ -n "$LE_EMAIL" ]] || die "Email Let's Encrypt wajib diisi jika memakai domain."
fi
TS_HOST=""
if [[ -z "$APP_DOMAIN" ]]; then
    ts_name=$(ts_cert_domain)
    if [[ -n "$ts_name" ]]; then
        ask TAILSCALE_HTTPS "Tailscale terdeteksi. Pakai HTTPS lewat Tailscale (https://${ts_name}, hanya dari perangkat di tailnet)? (y/n)" "y"
        [[ "$TAILSCALE_HTTPS" =~ ^[Yy] ]] && TS_HOST="$ts_name"
    elif command -v tailscale >/dev/null 2>&1; then
        warn "Tailscale terpasang tapi belum siap untuk HTTPS: belum login, atau MagicDNS/HTTPS Certificates"
        warn "belum aktif di admin console (DNS). Lanjut via IP; jalankan ulang script ini setelah diaktifkan."
    elif [[ "${TAILSCALE_HTTPS:-}" =~ ^[Yy] ]]; then
        warn "TAILSCALE_HTTPS=y tetapi perintah 'tailscale' tidak ditemukan. Lanjut via IP."
    fi
fi
ask DEMO_DATA "Isi data demo (akun & pasien fiktif) untuk uji coba? (y/n)" "y"
ask ENABLE_UFW "Aktifkan firewall UFW (SSH, HTTP, HTTPS)? (y/n)" "y"

if [[ "$DEMO_DATA" =~ ^[Yy] ]]; then APP_ENV_VALUE="staging"; else APP_ENV_VALUE="production"; fi

# ---------------------------------------------------------------------------
# 1. Paket sistem
# ---------------------------------------------------------------------------
step "Memasang paket sistem (PHP, MySQL, Redis, Nginx, Supervisor)"
apt-get update -qq
apt-get install -y -qq \
    ca-certificates curl git unzip openssl cron tzdata \
    nginx supervisor redis-server mysql-server \
    php-fpm php-cli php-mysql php-redis php-intl php-mbstring php-xml php-zip php-curl php-gd php-bcmath \
    composer certbot python3-certbot-nginx ufw >/dev/null
apt-get install -y -qq 7zip >/dev/null 2>&1 || apt-get install -y -qq p7zip-full >/dev/null 2>&1 || warn "7zip tidak terpasang (dipakai untuk restore backup)."

PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || die "Butuh PHP 8.3+, terpasang $PHP_VERSION."
FPM_SERVICE="php${PHP_VERSION}-fpm"
FPM_SOCKET="/run/php/php${PHP_VERSION}-fpm.sock"
ok "PHP $PHP_VERSION, $(mysqld --version | awk '{print $1, $3}'), $(redis-server --version | awk '{print $1, $3}'), $(nginx -v 2>&1 | cut -d' ' -f3)"

if has_systemd; then timedatectl set-timezone Asia/Jakarta || true; else ln -sf /usr/share/zoneinfo/Asia/Jakarta /etc/localtime; fi

cat > "/etc/php/${PHP_VERSION}/fpm/conf.d/99-dental-pms.ini" <<'INI'
; Dental PMS
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
max_execution_time = 60
expose_php = Off
INI

for s in mysql redis-server "$FPM_SERVICE" nginx supervisor cron; do svc enable "$s"; done
svc restart "$FPM_SERVICE"
ok "Layanan aktif"

# ---------------------------------------------------------------------------
# 2. Firewall
# ---------------------------------------------------------------------------
if [[ "$ENABLE_UFW" =~ ^[Yy] ]]; then
    step "Mengatur firewall (UFW)"
    ssh_ports=$(ss -tlnpH 2>/dev/null | awk '/sshd/ {n=split($4,a,":"); print a[n]}' | sort -u)
    [[ -n "$ssh_ports" ]] || ssh_ports="22"
    for p in $ssh_ports; do ufw allow "$p/tcp" >/dev/null; ok "SSH port $p dibuka"; done
    ufw allow 'Nginx Full' >/dev/null
    if ufw --force enable >/dev/null 2>&1; then ok "UFW aktif (SSH, 80, 443)"; else warn "UFW tidak bisa diaktifkan di lingkungan ini, dilewati."; fi
fi

# ---------------------------------------------------------------------------
# 3. Kode aplikasi
# ---------------------------------------------------------------------------
step "Mengambil kode aplikasi"
mkdir -p /var/www/.composer
chown -R "$APP_USER:$APP_USER" /var/www/.composer
if [[ -d "$APP_DIR/.git" ]]; then
    as_app git -C "$APP_DIR" fetch --quiet origin "$REPO_BRANCH"
    as_app git -C "$APP_DIR" checkout --quiet "$REPO_BRANCH"
    as_app git -C "$APP_DIR" pull --quiet --ff-only origin "$REPO_BRANCH"
    ok "Kode diperbarui ($(as_app git -C "$APP_DIR" rev-parse --short HEAD))"
else
    mkdir -p "$APP_DIR"
    chown "$APP_USER:$APP_USER" "$APP_DIR"
    as_app git clone --quiet --branch "$REPO_BRANCH" "$REPO_URL" "$APP_DIR"
    ok "Kode di-clone ($(as_app git -C "$APP_DIR" rev-parse --short HEAD))"
fi

# ---------------------------------------------------------------------------
# 4. Database
# ---------------------------------------------------------------------------
step "Menyiapkan database MySQL"
wait_for_mysql
wait_for_redis
if [[ ! -f "$APP_DIR/.env" ]]; then
    as_app cp "$APP_DIR/.env.example" "$APP_DIR/.env"
fi
DB_PASSWORD=$(env_get DB_PASSWORD || true)
if [[ -z "$DB_PASSWORD" ]]; then DB_PASSWORD=$(random_secret 32); fi

mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
-- Dibutuhkan mysqldump (backup) di MySQL 8.x.
GRANT PROCESS ON *.* TO '${DB_USER}'@'localhost';
GRANT PROCESS ON *.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
ok "Database '${DB_NAME}' dan user '${DB_USER}' siap"

# ---------------------------------------------------------------------------
# 4b. HTTPS lewat Tailscale (opsional, jika tanpa domain)
# ---------------------------------------------------------------------------
if [[ -n "$TS_HOST" ]]; then
    step "Mengaktifkan HTTPS lewat Tailscale (${TS_HOST})"
    ts_log=$(mktemp)
    # Tailscale menerima https://<nama>.ts.net:443, sertifikatnya diurus Tailscale, lalu meneruskan ke Nginx.
    if timeout 60 tailscale serve --bg http://127.0.0.1:80 </dev/null >"$ts_log" 2>&1; then
        ok "https://${TS_HOST} -> Nginx 127.0.0.1:80"
    else
        warn "tailscale serve gagal:"
        tail -n 5 "$ts_log" | sed 's/^/      /'
        warn "Butuh Tailscale 1.52+ dan HTTPS Certificates aktif. Lanjut via IP; jalankan ulang script setelah diperbaiki."
        TS_HOST=""
    fi
    rm -f "$ts_log"
fi

# ---------------------------------------------------------------------------
# 5. Konfigurasi .env
# ---------------------------------------------------------------------------
step "Menulis konfigurasi .env"
if [[ -n "$APP_DOMAIN" ]]; then
    APP_URL_VALUE="https://${APP_DOMAIN}"
elif [[ -n "$TS_HOST" ]]; then
    APP_URL_VALUE="https://${TS_HOST}"
else
    server_ip=$(hostname -I 2>/dev/null | awk '{print $1}')
    APP_URL_VALUE="http://${server_ip:-127.0.0.1}"
fi

env_set APP_ENV "$APP_ENV_VALUE"
env_set APP_DEBUG false
env_set APP_URL "$APP_URL_VALUE"
env_set LOG_LEVEL warning
env_set DB_CONNECTION mysql
env_set DB_HOST 127.0.0.1
env_set DB_PORT 3306
env_set DB_DATABASE "$DB_NAME"
env_set DB_USERNAME "$DB_USER"
env_set DB_PASSWORD "$DB_PASSWORD"
env_set QUEUE_CONNECTION redis
env_set CACHE_STORE redis
env_set SESSION_DRIVER database
if [[ -n "$TS_HOST" ]]; then
    # Tailscale tersambung ke Nginx dari localhost dan mengirim X-Forwarded-Proto: https.
    add_trusted_proxies 127.0.0.1 ::1
    env_set SESSION_SECURE_COOKIE true
elif [[ -z "$APP_DOMAIN" ]]; then
    # Akses via http://IP: cookie secure membuat login gagal.
    env_set SESSION_SECURE_COOKIE false
fi

# Kunci: hanya dibuat SEKALI. Mengganti kunci = data terenkripsi (NIK, MFA) tidak bisa dibaca.
[[ -n "$(env_get APP_KEY)" ]] || env_set APP_KEY "base64:$(openssl rand -base64 32)"
[[ -n "$(env_get BLIND_INDEX_KEY)" ]] || env_set BLIND_INDEX_KEY "$(openssl rand -base64 32)"
[[ -n "$(env_get BACKUP_ARCHIVE_PASSWORD)" ]] || env_set BACKUP_ARCHIVE_PASSWORD "$(random_secret 40)"
[[ -n "$(env_get BACKUP_DISK)" ]] || env_set BACKUP_DISK local
if [[ "$APP_ENV_VALUE" == "staging" && -z "$(env_get DEMO_PASSWORD)" ]]; then
    env_set DEMO_PASSWORD "$(random_secret 16)"
fi

ok ".env siap (APP_ENV=$APP_ENV_VALUE, APP_URL=$APP_URL_VALUE)"

# ---------------------------------------------------------------------------
# 6. Dependency PHP, migrasi, seed
# ---------------------------------------------------------------------------
step "Memasang dependency PHP (composer install, bisa beberapa menit)"
(cd "$APP_DIR" && as_app composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet)
ok "Dependency terpasang"

step "Migrasi database & data referensi"
artisan config:clear >/dev/null
artisan migrate --force --no-interaction
artisan db:seed --force --no-interaction >/dev/null
ok "Peran, hak akses, ICD, kondisi odontogram, satuan, penjamin terisi"

if [[ "$APP_ENV_VALUE" == "staging" ]]; then
    artisan db:seed --class=DemoSeeder --force --no-interaction >/dev/null
    ok "Data demo terisi"
fi

artisan storage:link --force >/dev/null 2>&1 || true
artisan optimize >/dev/null
artisan filament:optimize >/dev/null
chown -R "$APP_USER:$APP_USER" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
ok "Cache konfigurasi, route, view, dan komponen Filament dibuat"

# ---------------------------------------------------------------------------
# 7. Nginx
# ---------------------------------------------------------------------------
step "Mengatur Nginx"
# IPv6 hanya jika kernel mendukung; sebagian VPS mematikannya dan nginx akan gagal start.
HAS_IPV6=0
[[ -f /proc/net/if_inet6 ]] && HAS_IPV6=1
listen_lines() { # listen_lines "opsi"  -> baris listen IPv4 (+ IPv6)
    printf 'listen 80%s;' "$1"
    [[ "$HAS_IPV6" == 1 ]] && printf '\n    listen [::]:80%s;' "$1"
    return 0
}
if [[ -n "$TS_HOST" ]]; then
    # Aplikasi hanya untuk Tailscale (localhost); akses http://IP dialihkan ke alamat ts.net.
    SERVER_NAME="$TS_HOST"
    APP_LISTEN="listen 127.0.0.1:80;"
elif [[ -n "$APP_DOMAIN" ]]; then
    SERVER_NAME="$APP_DOMAIN"
    APP_LISTEN=$(listen_lines "")
else
    SERVER_NAME="_"
    APP_LISTEN=$(listen_lines " default_server")
fi
cat > /etc/nginx/sites-available/dental-pms <<NGINX
server {
    ${APP_LISTEN}
    server_name ${SERVER_NAME};
    root ${APP_DIR}/public;
    index index.php;

    client_max_body_size 25M;
    server_tokens off;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # Service worker harus selalu versi terbaru.
    location = /sw.js {
        add_header Cache-Control "no-cache" always;
        try_files \$uri =404;
    }

    location ~* \.(?:css|js|woff2?|png|svg|ico|webmanifest)$ {
        expires 7d;
        access_log off;
        try_files \$uri /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:${FPM_SOCKET};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 60;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX
if [[ -n "$TS_HOST" ]]; then
    cat >> /etc/nginx/sites-available/dental-pms <<NGINX

# Akses http://IP atau http://nama.ts.net dialihkan ke HTTPS Tailscale.
server {
    $(listen_lines " default_server")
    server_name _;
    server_tokens off;
    return 301 https://${TS_HOST}\$request_uri;
}
NGINX
fi
ln -sf /etc/nginx/sites-available/dental-pms /etc/nginx/sites-enabled/dental-pms
rm -f /etc/nginx/sites-enabled/default
nginx -t -q
svc reload nginx
ok "Nginx melayani ${SERVER_NAME} -> ${APP_DIR}/public"

# ---------------------------------------------------------------------------
# 8. HTTPS (Let's Encrypt)
# ---------------------------------------------------------------------------
HTTPS_OK=0
if [[ -n "$APP_DOMAIN" ]]; then
    step "Meminta sertifikat HTTPS untuk ${APP_DOMAIN}"
    if certbot --nginx -d "$APP_DOMAIN" -m "$LE_EMAIL" --agree-tos --non-interactive --redirect --quiet; then
        HTTPS_OK=1
        env_set SESSION_SECURE_COOKIE true
        artisan config:cache >/dev/null
        ok "HTTPS aktif, HTTP dialihkan ke HTTPS (perpanjangan otomatis oleh certbot)"
    else
        warn "Sertifikat gagal. Pastikan A record ${APP_DOMAIN} sudah mengarah ke IP server ini dan port 80 terbuka,"
        warn "lalu jalankan: certbot --nginx -d ${APP_DOMAIN} --redirect   (kemudian jalankan ulang script ini)"
        env_set APP_URL "http://${APP_DOMAIN}"
        env_set SESSION_SECURE_COOKIE false
        artisan config:cache >/dev/null
    fi
fi

# ---------------------------------------------------------------------------
# 9. Queue worker & scheduler
# ---------------------------------------------------------------------------
step "Mengatur queue worker (Supervisor) & scheduler (cron)"
cat > /etc/supervisor/conf.d/dental-pms-worker.conf <<SUP
[program:dental-pms-worker]
command=php ${APP_DIR}/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=${APP_DIR}
user=${APP_USER}
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=${APP_DIR}/storage/logs/worker.log
SUP
svc enable supervisor
for _ in $(seq 1 15); do supervisorctl status >/dev/null 2>&1 && break; sleep 1; done
supervisorctl reread >/dev/null
supervisorctl update >/dev/null
supervisorctl restart dental-pms-worker >/dev/null 2>&1 || true
ok "Worker: $(supervisorctl status dental-pms-worker | awk '{print $2}')"

cat > /etc/cron.d/dental-pms <<CRON
# Dental PMS scheduler (backup harian, cek SIP, pembersihan)
* * * * * ${APP_USER} cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1
CRON
chmod 644 /etc/cron.d/dental-pms
ok "Cron scheduler terpasang"

# ---------------------------------------------------------------------------
# 10. Akun owner
# ---------------------------------------------------------------------------
step "Akun Owner"
if artisan clinic:create-owner --check >/dev/null 2>&1; then
    ok "Akun owner sudah ada"
elif [[ -n "${OWNER_EMAIL:-}" && -n "${OWNER_PASSWORD:-}" ]]; then
    artisan clinic:create-owner --name="${OWNER_NAME:-Owner}" --email="$OWNER_EMAIL" --password="$OWNER_PASSWORD"
elif is_tty; then
    echo "    Buat akun Owner klinik (password min. 10 karakter, huruf + angka):"
    (cd "$APP_DIR" && runuser -u "$APP_USER" -- php artisan clinic:create-owner) || warn "Owner belum dibuat. Jalankan nanti: cd $APP_DIR && sudo -u www-data php artisan clinic:create-owner"
else
    warn "Owner belum dibuat. Jalankan: cd $APP_DIR && sudo -u www-data php artisan clinic:create-owner"
fi

# ---------------------------------------------------------------------------
# 11. Verifikasi
# ---------------------------------------------------------------------------
step "Verifikasi"
host_header="${APP_DOMAIN:-${TS_HOST:-localhost}}"
code=$(curl -s -o /dev/null -w '%{http_code}' -H "Host: ${host_header}" http://127.0.0.1/login)
if [[ -n "$TS_HOST" ]]; then
    [[ "$code" == "200" ]] && ok "Nginx lokal -> 200" || warn "Nginx lokal -> ${code} (lihat ${APP_DIR}/storage/logs/laravel.log)"
    # Permintaan pertama bisa lambat: Tailscale sedang mengambil sertifikat.
    code_ts=$(curl -s -o /dev/null -w '%{http_code}' --max-time 60 "https://${TS_HOST}/login" || true)
    if [[ "$code_ts" == "200" ]]; then
        ok "https://${TS_HOST}/login -> 200"
    else
        warn "https://${TS_HOST}/login -> ${code_ts:-000} dari server ini. Coba buka dari perangkat lain di tailnet."
    fi
elif [[ "$HTTPS_OK" == 1 ]]; then
    code_https=$(curl -s -o /dev/null -w '%{http_code}' "https://${APP_DOMAIN}/login" || echo "000")
    [[ "$code_https" == "200" ]] && ok "https://${APP_DOMAIN}/login -> 200" || warn "https://${APP_DOMAIN}/login -> ${code_https}"
else
    [[ "$code" == "200" ]] && ok "Halaman login -> 200" || warn "Halaman login -> ${code} (lihat ${APP_DIR}/storage/logs/laravel.log)"
fi
if artisan backup:run --only-db --no-interaction >/dev/null 2>&1; then
    ok "Backup database terenkripsi berhasil dibuat (disk: $(env_get BACKUP_DISK))"
else
    warn "Backup gagal. Cek: cd $APP_DIR && sudo -u www-data php artisan backup:run"
fi

# ---------------------------------------------------------------------------
# 12. Simpan kredensial
# ---------------------------------------------------------------------------
umask 077
{
    echo "Dental PMS — kredensial server ($(date '+%Y-%m-%d %H:%M'))"
    echo "SIMPAN di password manager owner, lalu HAPUS file ini dari server."
    echo "Tanpa APP_KEY + BLIND_INDEX_KEY + BACKUP_ARCHIVE_PASSWORD, backup tidak bisa dipulihkan."
    echo
    echo "URL                     : $(env_get APP_URL)"
    echo "APP_KEY                 : $(env_get APP_KEY)"
    echo "BLIND_INDEX_KEY         : $(env_get BLIND_INDEX_KEY)"
    echo "BACKUP_ARCHIVE_PASSWORD : $(env_get BACKUP_ARCHIVE_PASSWORD)"
    echo "DB ${DB_NAME} / ${DB_USER}  : $(env_get DB_PASSWORD)"
    if [[ "$APP_ENV_VALUE" == "staging" ]]; then
        echo
        echo "Akun demo (owner@, admin@, dokter@, dokter2@, perawat@, resepsionis@, kasir@demo.test)"
        echo "Password demo           : $(env_get DEMO_PASSWORD)"
    fi
} > "$CREDENTIALS_FILE"

echo
echo "${c_ok}Selesai.${c_off}"
echo "    Buka        : $(env_get APP_URL)"
[[ -n "$TS_HOST" ]] && echo "                  (hanya dari perangkat yang login Tailscale di tailnet yang sama)"
echo "    Kredensial  : ${CREDENTIALS_FILE}  (sudo cat ${CREDENTIALS_FILE})"
[[ "$APP_ENV_VALUE" == "staging" ]] && echo "    Akun demo   : owner@demo.test dkk. Password ada di file kredensial. MFA diatur saat login pertama."
echo "    Update kode : sudo bash ${APP_DIR}/deploy/update.sh"
echo
echo "    Langkah berikutnya (lihat docs/04-setup-deploy-backup.md):"
echo "    - Isi MAIL_* (SMTP) di ${APP_DIR}/.env agar email MFA, reset password & notifikasi backup terkirim."
echo "    - Isi BACKUP_DISK=backup + BACKUP_AWS_* agar backup tersimpan di luar server."
echo "      Setelah mengubah .env: sudo bash ${APP_DIR}/deploy/update.sh"
