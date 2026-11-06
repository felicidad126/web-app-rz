# Fase 4: Bridging SATUSEHAT — Catatan Implementasi

> Status: **selesai dari sisi kode** · 6 November 2026 · **belum diuji ke sandbox Kemenkes** (butuh kredensial klinik)
> Acuan: [Planning v2 §5.5 & §7, Fase 4](./02-dental-pms-planning-v2.md#fase-4-bridging-satusehat-minggu-910) · tampilan mengikuti mockup v2 "SATUSEHAT"

---

## 1. Yang Sudah Dibangun

| Checklist Fase 4 | Status | Di aplikasi |
|---|---|---|
| Evaluasi `ivanwilliammd/satusehat-integration` vs HTTP client sendiri | ✅ | Memakai **client sendiri** (`App\Services\SatuSehat\SatuSehatClient`). Alasan di §4 no. 1. |
| Auth OAuth2 + penyimpanan token, pencarian IHS pasien & nakes | ✅ | Token *client credentials* di-cache (Redis) sampai 2 menit sebelum kedaluwarsa, diperbarui sekali otomatis bila ditolak 401. IHS dicari dari NIK saat kunjungan dikirim, atau lewat tombol **Cari IHS SATUSEHAT** di halaman pasien & nakes. Disimpan di `satusehat_ihs_id`. |
| Job kirim Encounter, Condition, Observation, Procedure, MedicationRequest + `satusehat_syncs` + retry | ✅ | Setelah dokter menandatangani rekam medis, kunjungan masuk antrean dan dikirim sebagai **satu Bundle transaksi** (detail di §2). Gangguan jaringan/server dicoba ulang otomatis (1 mnt, 5 mnt, 15 mnt, 1 jam, 6 jam), lalu tiap jam lewat `satusehat:retry`. |
| Halaman monitoring gagal kirim + kirim ulang | ✅ | **Operasional → SATUSEHAT**: daftar *Perlu diperbaiki* dengan penyebab & cara memperbaiki, rekap hari ini per jenis data, pengiriman terakhir, log semua pengiriman (filter, kirim ulang per baris atau massal). Tombol **Tes koneksi** dan **Kirim kunjungan lama**. Widget SATUSEHAT di dashboard. |
| Location cabang | ✅ | **Organisasi → Cabang → Daftarkan ke SATUSEHAT** (membuat Location "Poli Gigi …"), atau isi Location ID dari SATUSEHAT Platform. |
| Uji di sandbox → produksi, backfill | ⏳ | Menunggu kredensial sandbox klinik. Backfill tersedia: `php artisan satusehat:backfill 2026-11-01` atau tombol **Kirim kunjungan lama**. |

**DoD Fase 4** (semua kunjungan baru terkirim otomatis ke produksi, kegagalan terlihat di dashboard): kode dan monitoring siap; tinggal diisi kredensial, diuji di sandbox, lalu dipindah ke produksi.

---

## 2. Isi yang Dikirim (per kunjungan)

Satu **Bundle `transaction`** ke `POST {FHIR base}`; antar-resource saling merujuk dengan `urn:uuid:`. Bundle diterima semua atau ditolak semua, jadi tidak ada data setengah terkirim.

| Data aplikasi | Resource FHIR | Kode / sistem |
|---|---|---|
| Kunjungan | `Encounter` status *finished* + `statusHistory` arrived → in-progress → finished, kelas `AMB`, Location cabang, `serviceProvider` organisasi | identifier `http://sys-ids.kemkes.go.id/encounter/{org}` = nomor kunjungan |
| Diagnosis | `Condition` (*encounter-diagnosis*), urutan: utama dulu; nomor gigi di `note` | ICD-10 `http://hl7.org/fhir/sid/icd-10` |
| Tanda vital | `Observation` (*vital-signs*), satu per tanda: sistolik, diastolik, nadi, napas, suhu | LOINC 8480-6, 8462-4, 8867-4, 9279-1, 8310-5 · UCUM |
| Tindakan | `Procedure` *completed*, operator tiap tindakan sebagai performer; gigi & permukaan di `note` | ICD-9-CM `http://hl7.org/fhir/sid/icd-9-cm` |
| Resep | `Medication` (+ ekstensi tipe *NC*) dan `MedicationRequest` (identifier resep & item resep) | KFA `http://sys-ids.kemkes.go.id/kfa` |

Semua waktu dikirim dalam **UTC (+00:00)** sesuai katalog validasi SATUSEHAT, dan urutan waktu dijamin naik (datang < diperiksa < selesai).

### Kapan kunjungan tertahan ("Perlu diperbaiki")

| Penyebab | Cara memperbaiki |
|---|---|
| Pasien belum punya NIK / NIK tidak ditemukan | Lengkapi/periksa NIK di data pasien, lalu **Kirim ulang** |
| Dokter belum punya NIK / tidak ditemukan | Lengkapi NIK di data nakes |
| Tindakan belum punya kode ICD-9-CM | Petakan di **Tindakan & Tarif**; kirim ulang memakai kode master terbaru |
| Cabang belum punya Location ID | **Daftarkan ke SATUSEHAT** di halaman cabang |
| Ditolak validasi SATUSEHAT | Pesan dari SATUSEHAT ditampilkan (NIK di pesan disamarkan) |
| Sudah ada di SATUSEHAT (duplikat) | Biasanya karena kiriman sebelumnya sempat terputus. Cek di portal; tombol **Tandai sudah diterima** (tercatat di audit) |
| Kredensial ditolak | Periksa `.env` |

Obat **tanpa kode KFA** tidak menahan kunjungan: obat itu dilewati dan dicatat sebagai catatan pada kiriman.

---

## 3. Mengaktifkan di Server

1. Daftarkan klinik di SATUSEHAT Platform, lalu ambil **Organization ID**, **Client ID**, dan **Client Secret** (sandbox dulu).
2. Isi di `/var/www/dental-pms/.env`:
   ```
   SATUSEHAT_ENABLED=true
   SATUSEHAT_ENV=sandbox        # ganti "production" setelah lulus uji
   SATUSEHAT_ORGANIZATION_ID=...
   SATUSEHAT_CLIENT_ID=...
   SATUSEHAT_CLIENT_SECRET=...
   ```
   lalu `sudo bash /var/www/dental-pms/deploy/update.sh` (memperbarui cache config dan me-restart worker).
3. Buka **Operasional → SATUSEHAT → Tes koneksi**.
4. Isi Location tiap cabang (**Organisasi → Cabang**).
5. Lengkapi NIK dokter, cari IHS-nya (**Tenaga Kesehatan → Ubah → Cari IHS SATUSEHAT**).
6. Tanda tangani satu kunjungan uji, lihat hasilnya di halaman SATUSEHAT dan di portal sandbox.
7. Setelah lulus: ganti ke `production` + kredensial produksi, lalu kirim kunjungan sejak soft launch dengan **Kirim kunjungan lama**.

Queue worker (Supervisor) dan scheduler (cron) sudah dipasang oleh `deploy/provision.sh`.

---

## 4. Keputusan & Penyimpangan

| # | Planning | Implementasi | Alasan |
|---|---|---|---|
| 1 | Evaluasi library `ivanwilliammd/satusehat-integration` | **Client sendiri** (±200 baris). | Library versi terbaru (4.15.x) sudah mendukung Laravel 13, tetapi mencatat URL & payload permintaan ke tabel log-nya. URL pencarian pasien berisi NIK, sehingga melanggar aturan NIK & UU PDP. Library juga butuh tabel & env sendiri. Client sendiri lebih mudah diuji (`Http::fake`) dan tidak pernah menulis URL/payload ke log. |
| 2 | Kredensial di tabel `settings` terenkripsi | Kredensial di **`.env` server** | Belum ada halaman pengaturan; rahasia tidak masuk database maupun backup DB. Halaman SATUSEHAT menampilkan status & tes koneksi. |
| 3 | Encounter dikirim saat check-in, lalu di-update (in-progress/finished) | **Satu Bundle setelah tanda tangan**: Encounter *finished* + `statusHistory` lengkap | Pola resmi Postman "Bundle Rawat Jalan". Satu permintaan per kunjungan, atomik, mudah dikirim ulang, dan rekam medis yang dikirim sudah final (terkunci). Kunjungan yang dibatalkan tidak pernah terkirim. |
| 4 | `satusehat_syncs` per resource | **Satu baris per kunjungan**; ID tiap resource (Encounter, Condition, …) disimpan di kolom JSON `resource_ids`, jumlah per jenis di `resource_counts` | Bundle diterima/ditolak sebagai satu kesatuan. Rekap per jenis data di monitoring tetap tersedia. |
| 5 | Condition untuk keluhan (SNOMED) | Belum dikirim; keluhan tetap di rekam medis | Keluhan disimpan sebagai teks bebas, belum ada pemetaan SNOMED. |
| 6 | — | Koreksi lewat **addendum** setelah terkirim **tidak dikirim ulang** ke SATUSEHAT | Butuh update resource (PUT) per jenis; masuk backlog bila diwajibkan. |
| 7 | — | Modul **Rawat Jalan Gigi** (odontogram sebagai Observation) belum dikirim | Spesifikasi terminologinya belum bisa diverifikasi; masuk bersama odontogram visual (Fase 7). |
| 8 | — | `Medication.form` dan `dosageInstruction` terstruktur (frekuensi, rute) belum dikirim; aturan pakai dikirim sebagai teks | Master obat belum menyimpan bentuk sediaan & rute. **Pastikan di sandbox** apakah teks saja diterima. |

---

## 5. Verifikasi

- **Test otomatis: 141 test, 576 assertion** (Fase 3: 123). Yang baru di `tests/Feature/SatuSehatTest.php`, memakai tiruan API (`Http::fake`):
  - kunjungan masuk antrean setelah tanda tangan; tidak ada apa pun saat dimatikan
  - isi Bundle: identifier Encounter, kelas AMB, Location, statusHistory, waktu UTC, referensi `urn:uuid`, kode ICD-10 / ICD-9-CM / LOINC / KFA, nilai tanda vital dari "120/80"
  - ID dari balasan tersimpan per resource; kirim ulang tidak membuat permintaan baru
  - tertahan dengan panduan: NIK kosong, NIK tidak ditemukan, tindakan tanpa ICD-9-CM, cabang tanpa Location
  - 503 → dicoba ulang oleh queue; 400 → menunggu perbaikan; NIK di pesan error disamarkan; duplikat → "Tandai sudah diterima"
  - token di-cache dan diperbarui sekali saat 401
  - halaman monitoring (kirim ulang, tes koneksi), backfill, pendaftaran Location, cari IHS dari halaman pasien, akses per peran
- **Larastan** level 5 bersih, **Pint** bersih.
- **Uji browser:** halaman SATUSEHAT, widget dashboard, tombol di halaman cabang.
- **Sumber spesifikasi:** payload Postman resmi Kemenkes (`satusehat/satusehat-public`, salinan per 19-08-2026) dan katalog validasi SATUSEHAT. Situs dokumentasi Kemenkes tidak bisa diakses langsung dari lingkungan pengembangan, jadi **semua format wajib dikonfirmasi di sandbox**.

---

## 6. Hak Akses (tambahan Fase 4)

| | Owner | Admin | Dokter | Perawat | Resepsionis | Kasir |
|---|---|---|---|---|---|---|
| Halaman & widget SATUSEHAT | R | R | — | R | R | — |
| Kirim ulang, tes koneksi, kirim kunjungan lama | — | ✅ | — | — | ✅ | — |
| Cari IHS pasien | ✅ (ubah pasien) | ✅ | ✅ | ✅ | ✅ | — |
| Cari IHS nakes, daftarkan Location cabang | ✅ | ✅ | — | — | — | — |

Izin tanpa resource: `View:SatuSehat`, `Resend:SatuSehat` (di `RolePermissionSeeder`).
