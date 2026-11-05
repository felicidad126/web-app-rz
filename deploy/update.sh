#!/usr/bin/env bash
#
# Dental PMS — update kode di server yang sudah di-provision.
#
#   sudo bash /var/www/dental-pms/deploy/update.sh
#
# Juga dipakai setelah mengubah .env (agar cache konfigurasi diperbarui).
#
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/dental-pms}"
APP_USER="www-data"
REPO_BRANCH="${REPO_BRANCH:-$(runuser -u "$APP_USER" -- git -C "$APP_DIR" rev-parse --abbrev-ref HEAD)}"

c_info=$'\e[1;36m'; c_ok=$'\e[1;32m'; c_err=$'\e[1;31m'; c_off=$'\e[0m'
step() { echo "${c_info}==> $*${c_off}"; }
as_app() { runuser -u "$APP_USER" -- env HOME=/var/www COMPOSER_HOME=/var/www/.composer "$@"; }
artisan() { (cd "$APP_DIR" && as_app php artisan "$@"); }

[[ $EUID -eq 0 ]] || { echo "Jalankan sebagai root: sudo bash $0" >&2; exit 1; }

IN_MAINTENANCE=0
on_error() {
    echo "${c_err}ERROR di baris $1: $2${c_off}" >&2
    if [[ "$IN_MAINTENANCE" == 1 ]]; then
        echo "Aplikasi masih dalam mode maintenance. Setelah masalah diperbaiki: cd $APP_DIR && sudo -u $APP_USER php artisan up" >&2
    fi
}
trap 'on_error $LINENO "$BASH_COMMAND"' ERR

step "Mengambil kode terbaru ($REPO_BRANCH)"
before=$(as_app git -C "$APP_DIR" rev-parse --short HEAD)
as_app git -C "$APP_DIR" fetch --quiet origin "$REPO_BRANCH"
as_app git -C "$APP_DIR" checkout --quiet "$REPO_BRANCH"
as_app git -C "$APP_DIR" pull --quiet --ff-only origin "$REPO_BRANCH"
after=$(as_app git -C "$APP_DIR" rev-parse --short HEAD)
echo "    $before -> $after"

step "Mode maintenance"
artisan down --retry=15 --refresh=15 >/dev/null
IN_MAINTENANCE=1

step "Dependency PHP"
(cd "$APP_DIR" && as_app composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet)

step "Migrasi & sinkronisasi hak akses"
artisan config:clear >/dev/null
artisan migrate --force --no-interaction
artisan db:seed --class=RolePermissionSeeder --force --no-interaction >/dev/null
artisan db:seed --class=ReferenceDataSeeder --force --no-interaction >/dev/null

step "Cache & restart layanan"
artisan optimize >/dev/null
artisan filament:optimize >/dev/null
chown -R "$APP_USER:$APP_USER" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
artisan queue:restart >/dev/null
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
if [[ -d /run/systemd/system ]]; then systemctl reload "php${PHP_VERSION}-fpm"; else service "php${PHP_VERSION}-fpm" reload >/dev/null; fi

artisan up >/dev/null
IN_MAINTENANCE=0
echo "${c_ok}Update selesai ($after).${c_off}"
