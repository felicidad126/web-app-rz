# Fase 1: Fondasi & Master Data — Catatan Implementasi

> Status: **selesai dari sisi kode** · 4 November 2026
> Acuan: [Planning v2 §7, Fase 1](./02-dental-pms-planning-v2.md#fase-1-fondasi--master-data-minggu-23)
> Cara menjalankan & deploy: [04-setup-deploy-backup.md](./04-setup-deploy-backup.md)

---

## 1. Yang Sudah Dibangun

| Checklist Fase 1 | Status | Implementasi |
|---|---|---|
| Laravel 13 + Filament v5 + MySQL + Redis + queue + scheduler | ✅ | Laravel 13.40, Filament 5.11, Livewire 4.4. Queue & cache ke Redis. Jadwal di `routes/console.php`. |
| CI (Pest, Larastan, Pint) | ✅ | `.github/workflows/ci.yml`: Pint + Larastan (level 5) + Pest di PHP 8.3 & 8.4 dengan MySQL 8.4 |
| Role & permission (Shield) | ✅ | 6 peran (`owner`, `admin`, `dokter`, `perawat`, `resepsionis`, `kasir`) + `super_admin` untuk IT. Matriks di `database/seeders/RolePermissionSeeder.php`. |
| MFA wajib + session timeout | ✅ | Semua pengguna wajib mengatur MFA (aplikasi authenticator dengan recovery code, atau kode via email). Idle timeout 30 menit, session terenkripsi. |
| Cabang, kursi, user–cabang, profil nakes | ✅ | Menu *Organisasi*. Peringatan SIP: widget dashboard + notifikasi mingguan ke Owner/Admin. |
| Audit log + soft delete + `number_sequences` | ✅ | Semua perubahan data tercatat (tanpa NIK/password). Membuka data pasien juga tercatat (log `access`). Tidak ada hapus permanen. |
| CRUD pasien | ✅ | Nomor RM otomatis, unik lintas cabang. NIK terenkripsi + blind index (bisa dicari, cek duplikat). Persetujuan data (UU PDP) wajib saat registrasi. |
| CRUD tindakan, obat & bahan, penjamin | ✅ | Tindakan dengan ICD-9-CM, tarif dasar, override tarif per cabang, durasi. Obat & bahan dengan kode KFA, satuan dasar, konversi satuan. |
| Seed ICD-10, ICD-9-CM, kondisi odontogram | ✅ | Daftar awal kode gigi + 28 draft kode kondisi odontogram. Daftar lengkap diimpor lewat CSV. |
| Import master dari Excel klinik | ✅ | Tombol **Import CSV** di Pasien, Tindakan, Obat & Bahan, ICD-10, ICD-9-CM (dari Excel: *Save As → CSV*). |
| Manifest PWA + service worker + ikon | ✅ | Bisa di-install. Service worker hanya meng-cache aset statis; halaman data selalu ke jaringan. |
| Backup harian terenkripsi + uji restore | ✅ (lokal) | Arsip ZIP AES-256 berisi database + file klinis. Uji restore ke database terpisah: semua tabel kembali utuh. |

### Hasil verifikasi

- **Test otomatis:** 50 test, 126 assertion, lulus di SQLite dan MySQL 8.0 lokal. Mencakup:
  - nomor RM
  - enkripsi & pencarian NIK
  - NIK tidak bocor ke audit log
  - validasi form pasien
  - matriks akses per peran
  - MFA wajib
  - pengguna nonaktif
  - konversi satuan & tarif cabang
  - import parser
  - PWA
  - notifikasi SIP
- **Analisis statis:** Larastan level 5 tanpa error. Pint bersih.
- **Uji browser (headless Chromium):** login + kode MFA, lalu membuka 22 halaman panel. Semuanya HTTP 200 tanpa error JavaScript. Pencarian NIK lengkap menemukan tepat 1 pasien. Service worker terdaftar di scope `/`.
- **Uji backup:**
  - `backup:run` berhasil dan arsip lolos verifikasi.
  - Arsip **tidak bisa dibuka tanpa password**.
  - Restore ke database baru: jumlah baris `patients`, `users`, `roles`, `permissions`, `icd10_codes`, `treatments`, `items`, dan `activity_log` identik dengan aslinya.

### Belum terverifikasi (butuh lingkungan nyata)

- **Install PWA di HP/tablet asli.** Butuh domain HTTPS. Dijadwalkan di Fase 7, tapi sebaiknya dicoba begitu staging online.
- **Backup ke object storage S3.** Butuh kredensial bucket. Konfigurasinya sudah siap (disk `backup`).
- **Pengiriman email** (MFA via email, reset password, notifikasi backup gagal). Butuh SMTP.
- **Master data asli klinik.** Menunggu file dari klinik (Fase 0).

---

## 2. Keputusan & Penyimpangan dari Planning v2

| # | Planning v2 | Implementasi | Alasan |
|---|---|---|---|
| 1 | PHP 8.4 | Kode jalan di **PHP 8.3+**, CI menguji 8.3 & 8.4. Produksi tetap disarankan 8.4. | `spatie/laravel-activitylog` dipakai versi 4.x (v5 mewajibkan PHP 8.4). Upgrade ke v5 bisa dilakukan setelah minimum PHP dinaikkan. |
| 2 | Tabel `actions`, `action_branch_prices` | **`treatments`**, **`treatment_branch_prices`** | Menghindari bentrok nama dengan kelas `Filament\Actions\Action`. Planning v2 sudah disesuaikan. |
| 3 | Resepsionis: CRUD pasien | Resepsionis: **tambah & ubah**, tanpa hapus | Data pasien bagian dari rekam medis dengan retensi panjang. Hapus (soft delete) hanya Owner/Admin. |
| 4 | Import dari Excel | Import **CSV** (fitur bawaan Filament) | Tidak menambah dependensi. Ada pemetaan kolom, validasi per baris, dan file baris gagal yang bisa diunduh. Excel cukup di-*Save As* CSV. |
| 5 | Seed ICD lengkap | **Daftar awal** kode gigi (`database/data/*.csv`) + import CSV | Daftar resmi lengkap sebaiknya diambil dari Kemenkes/SATUSEHAT. Terjemahan Indonesia di daftar awal perlu direview dokter/perekam medis. |
| 6 | STR + SIP dengan masa berlaku | **Hanya SIP** yang punya tanggal kedaluwarsa | Sejak UU 17/2023, STR berlaku seumur hidup. SIP tetap ada masa berlakunya. |
| 7 | — | Panel di root `/` (bukan `/admin`) | Satu aplikasi untuk semua staf. URL lebih pendek untuk PWA. |
| 8 | — | Avatar inisial dibuat lokal | Avatar bawaan Filament memanggil ui-avatars.com, yang berarti nama staf terkirim ke pihak ketiga. |
| 9 | — | Peran `super_admin` terpisah dari `owner` | `super_admin` untuk developer/IT (akses penuh via gate, tidak terlihat oleh Owner). Owner punya hak eksplisit sesuai matriks. |

---

## 3. Struktur Kode

```
app/
├── Console/Commands/        clinic:create-owner, clinic:check-sip-expiry
├── Enums/                   Gender, PractitionerType, PayerType, TreatmentCategory, ItemType, ...
├── Filament/
│   ├── Imports/             Importer CSV (pasien, tindakan, obat, ICD)
│   ├── Resources/           Satu folder per menu (Schemas/, Tables/, Pages/, RelationManagers/)
│   ├── Support/             NavGroup, NikField, CodeListSchema, InitialsAvatarProvider
│   └── Widgets/             SipExpiryWidget
├── Models/
│   └── Concerns/            HasEncryptedNik (enkripsi + blind index), LogsAudit (audit trail)
├── Policies/                Dibuat oleh Filament Shield (jangan diedit manual)
├── Rules/UniqueNik.php
├── Services/NumberSequence.php   Nomor urut aman dari race condition
└── Support/BlindIndex.php
database/
├── data/                    CSV referensi awal (ICD-10, ICD-9-CM)
└── seeders/                 RolePermissionSeeder, ReferenceDataSeeder, DemoSeeder
public/
├── manifest.webmanifest, sw.js, offline.html, icons/
```

### Aturan yang dijaga di kode

- **Tidak ada hapus permanen.** Policy Shield tidak punya `forceDelete`, dan tombolnya tidak ditampilkan.
- **NIK** hanya lewat `HasEncryptedNik`. Kolom `nik` terenkripsi, `nik_hash` untuk pencarian. NIK dikecualikan dari audit log dan ditandai *sensitive* di importer.
- **Uang** disimpan sebagai `bigint` rupiah.
- **Lazy loading dimatikan** di luar produksi (`Model::shouldBeStrict`), supaya N+1 query ketahuan sejak development.
- **Label UI Bahasa Indonesia.** Filament memakai locale `id`.

---

## 4. Cara Pakai Singkat

1. Login (email + password), lalu atur MFA saat login pertama. Pilih aplikasi authenticator (Google Authenticator/Authy) atau kode via email. Simpan recovery code.
2. **Owner/Admin**:
   - *Organisasi → Cabang*: isi cabang dan kursi.
   - *Pengaturan & Akses → Pengguna*: buat akun staf, pilih peran dan cabang.
   - *Organisasi → Tenaga Kesehatan*: lengkapi profil dokter/perawat (SIP, NIK).
3. **Master data**: *Master Klinis → Tindakan & Tarif* dan *Obat & Bahan → Obat & Bahan*. Isi manual atau **Import CSV**.
4. **Resepsionis**: *Pasien → Data Pasien → Pasien baru*. Nomor RM terisi otomatis, dan persetujuan data wajib dicentang.
5. Staf yang kehilangan HP authenticator: Owner/Admin membuka *Pengguna*, lalu menekan **Reset MFA**.

### Format CSV import

| Menu | Kolom (header bebas, dipetakan saat import) |
|---|---|
| Pasien | `no_rm` (opsional, mempertahankan nomor lama), `nama`*, `nik`, `tempat_lahir`, `tanggal_lahir`* (dd/mm/yyyy atau yyyy-mm-dd), `jenis_kelamin`* (L/P), `hp`, `email`, `alamat`, `pekerjaan`, `alergi`, `penyakit` |
| Tindakan | `kode`*, `nama`*, `kategori`, `icd9cm`, `tarif`*, `durasi` |
| Obat & Bahan | `kode`*, `nama`*, `jenis` (obat/bahan/alat/retail), `kfa`, `satuan`*, `stok_min`, `harga_jual` |
| ICD-10 / ICD-9-CM | `kode`*, `nama`*, `nama_indonesia` |

\* wajib. Import diproses di background, jadi **queue worker harus berjalan**. Baris dengan kode/no. RM yang sama akan di-update, bukan diduplikasi.

---

## 5. Sebelum Lanjut ke Fase 2

1. Kirim pertanyaan discovery (Planning v2 §10) ke klinik. Yang paling berdampak ke Fase 2 dan 3:
   - simbol odontogram yang dipakai
   - aturan fee dokter
   - skema paket/cicilan behel
   - model printer struk
2. Review draft **kode kondisi odontogram** (menu *Kondisi Odontogram*) bersama dokter gigi klinik.
3. Siapkan **staging dengan HTTPS** untuk uji install PWA di tablet dokter.
4. Siapkan **bucket object storage** dan **SMTP**, lalu isi variabel `BACKUP_*` dan `MAIL_*`.
