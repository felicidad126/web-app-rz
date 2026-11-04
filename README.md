# Dental PMS

Sistem manajemen klinik gigi berbasis PWA untuk klinik 1–3 cabang. Dibangun dengan Laravel 13 + FilamentPHP v5 (Livewire 4).

**Status:** Fase 1 (Fondasi & Master Data) selesai. Lihat [catatan implementasi](docs/03-fase-1-implementasi.md).

## Mulai Cepat

```bash
composer install && cp .env.example .env && php artisan key:generate
```

Isi `DB_*`, `BLIND_INDEX_KEY`, dan `BACKUP_ARCHIVE_PASSWORD` di `.env`, lalu:

```bash
php artisan migrate --seed && php artisan db:seed --class=DemoSeeder && php artisan serve
```

Login dengan `owner@demo.test` / `password`, lalu atur MFA. Detail lengkap ada di [docs/04-setup-deploy-backup.md](docs/04-setup-deploy-backup.md).

## Dokumen

| File | Isi |
|---|---|
| [`docs/00-dental-pms-planning.md`](docs/00-dental-pms-planning.md) | Planning v1 (arsip) |
| [`docs/01-review-planning.md`](docs/01-review-planning.md) | Review planning v1 + riset tech stack, regulasi, dan kompetitor |
| [`docs/02-dental-pms-planning-v2.md`](docs/02-dental-pms-planning-v2.md) | **Planning v2 (acuan aktif)**: scope, stack, spesifikasi modul, skema DB, roadmap 16 minggu |
| [`docs/03-fase-1-implementasi.md`](docs/03-fase-1-implementasi.md) | Hasil Fase 1: fitur, verifikasi, keputusan teknis, cara pakai |
| [`docs/04-setup-deploy-backup.md`](docs/04-setup-deploy-backup.md) | Setup lokal, deploy produksi, variabel `.env`, backup & restore |

## Pemeriksaan Kode

```bash
composer check
```

Menjalankan Pint, Larastan (level 5), dan Pest. CI menjalankan hal yang sama di PHP 8.3 & 8.4 dengan MySQL 8.4.
