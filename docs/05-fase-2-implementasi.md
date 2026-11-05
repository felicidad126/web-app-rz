# Fase 2: Jadwal, Kunjungan & Rekam Medis — Catatan Implementasi

> Status: **selesai dari sisi kode** · 6 November 2026
> Acuan: [Planning v2 §7, Fase 2](./02-dental-pms-planning-v2.md#fase-2-jadwal-kunjungan--rekam-medis-minggu-46) · tampilan mengikuti mockup v2 (Jadwal & antrean, Pemeriksaan)

---

## 1. Yang Sudah Dibangun

| Checklist Fase 2 | Status | Di aplikasi |
|---|---|---|
| Jadwal praktik dokter + pengecualian | ✅ | **Organisasi → Nakes → (ubah) → tab Jadwal praktik / Cuti & libur.** Per hari, cabang, kursi (opsional), jam. Cuti seharian atau beberapa jam. |
| Kalender janji temu + cek bentrok | ✅ | **Harian → Jadwal & antrean**: papan per kursi seperti mockup. Warna kartu = jenis perawatan, titik = status. Tombol **+** di tiap kursi untuk buat janji. Bentrok dokter/kursi/pasien ditolak. Di luar jadwal praktik atau saat cuti ditolak, kecuali dicentang "tetap simpan". Daftar lengkap: **Harian → Janji temu**. |
| Walk-in + papan antrean | ✅ | Tombol **Pasien datang langsung** di papan jadwal. Check-in janji → nomor antrean per cabang per hari + nomor kunjungan `KJ-YYMM-NNNN`. **Harian → Papan antrean** untuk layar ruang tunggu (nama pasien disamarkan, mis. "Siti A."). |
| SOAP + diagnosis ICD-10 + tindakan ICD-9-CM (snapshot tarif) | ✅ | **Kunjungan → Pemeriksaan**: anamnesis (S), pemeriksaan (O), tanda vital, penilaian (A), rencana (P), diagnosis per gigi, tindakan per gigi + permukaan (M O D V L), operator & asisten. Kode ICD-9-CM dan tarif cabang dikunci saat tindakan disimpan. |
| Odontogram input tabel + riwayat + cetak | ✅ | Tab **Odontogram** di pemeriksaan (catatan per gigi/permukaan; kondisi / rencana / dikerjakan). **Pasien → Odontogram**: status terkini per gigi, DMF-T & def-t otomatis, riwayat, **Cetak PDF**. Catatan lama tidak pernah ditimpa. |
| Resep (KFA) + lampiran (storage privat) | ✅ | Bagian **Resep** di pemeriksaan (obat, jumlah, satuan dari konversi, aturan pakai; kode KFA di-snapshot). Tab **Lampiran** (rontgen, foto, dokumen) disimpan di disk privat, dibuka lewat `/berkas/...` yang memeriksa hak akses dan mencatat akses. |
| Informed consent (template, signature pad, PDF) | ✅ | **Master Klinis → Template persetujuan** (teks + placeholder `{{nama_pasien}}`, `{{tindakan}}`, dst.). Tab **Persetujuan tindakan**: isi terisi otomatis, tanda tangan dengan jari/stylus, PDF dibuat dan disimpan. Isi tidak bisa diubah setelah ditandatangani. |
| Rencana perawatan + recall | ✅ | **Klinis → Rencana perawatan**: item per fase, gigi, permukaan, estimasi. Status draf → diajukan → disetujui/ditolak → sebagian selesai → selesai. Item disetujui bisa dipilih saat mencatat tindakan dan otomatis ditandai selesai. **Harian → Recall**: daftar kontrol jatuh tempo, aksi Dihubungi / Buat janji / Batal. Recall dibuat otomatis untuk tindakan yang punya "interval recall" (kolom baru di master tindakan). |
| Tanda tangan & penguncian, addendum, audit akses | ✅ | Tombol **Tanda tangani & kunci** (hanya dokter, minta password). Jendela tanda tangan menampilkan daftar periksa. Yang wajib hanya **keluhan (S)** dan **minimal satu diagnosis ICD-10**. Kolom password baru muncul setelah keduanya terisi. Setelah itu semua isian terkunci di level model, bukan hanya di layar. Koreksi lewat tab **Addendum** (tidak bisa diubah/dihapus). Membuka rekam medis, odontogram, lampiran, dan PDF tercatat di log `access`. |

**DoD Fase 2** — janji temu → check-in → panggil ke kursi → periksa → tanda tangan — sudah berjalan end-to-end dan diuji otomatis (lihat §3). Uji langsung di tablet dokter menunggu staging.

---

## 2. Hak Akses (tambahan Fase 2)

| Modul | Owner | Admin | Dokter | Perawat | Resepsionis | Kasir |
|---|---|---|---|---|---|---|
| Jadwal, janji temu, kunjungan | R | CRUD | R (daftar: pasiennya sendiri) + panggil ke kursi | RU | CRU | R |
| Rekam medis, odontogram, resep, lampiran, persetujuan | R | — | CRU + **tanda tangan** | CRU (draf) | — | — |
| Rencana perawatan, recall | R | recall CRUD | CRU | CRU | recall CRU | — |
| Template persetujuan | CRUD | CRUD | R | R | — | — |

Izin rekam medis memakai permission tanpa resource: `View:MedicalRecord`, `Update:MedicalRecord`, `Sign:MedicalRecord` (diatur di `RolePermissionSeeder`).

---

## 3. Verifikasi

- **Test otomatis: 86 test, 276 assertion** (Fase 1: 59). Yang baru antara lain:
  - cek bentrok & jadwal praktik/cuti
  - nomor antrean & nomor kunjungan
  - penguncian rekam medis (ubah/tambah/hapus setelah tanda tangan ditolak)
  - addendum
  - item rencana → selesai, recall otomatis
  - odontogram terkini & DMF-T
  - consent + PDF di disk privat
  - matriks akses halaman baru
  - form pemeriksaan → tanda tangan → terkunci, lewat UI Filament
  - perawat tidak bisa tanda tangan
  - daftar periksa tanda tangan (password hanya diminta jika keluhan & diagnosis lengkap)
  - nama di papan antrean disamarkan
- **Analisis statis:** Larastan level 5 bersih. Pint bersih. `composer audit` bersih (Symfony filesystem & html-sanitizer dinaikkan ke 7.4.23).
- **Uji browser (Chromium):** login + MFA sebagai resepsionis dan dokter. Halaman berikut dibuka tanpa error JavaScript/HTTP 500:
  - papan jadwal (pilih kartu → panel detail)
  - janji temu, kunjungan, papan antrean, recall
  - pemeriksaan, odontogram pasien, rencana perawatan

---

## 4. Keputusan & Penyimpangan

| # | Planning | Implementasi | Alasan |
|---|---|---|---|
| 1 | Dokter: jadwal "R (miliknya)" | Daftar janji & kunjungan dokter dibatasi pasiennya sendiri, tetapi **rekam medis pasien dokter lain tetap bisa dibuka** lewat riwayat pasien | Menggantikan rekan / konsultasi antar dokter. Setiap akses tercatat. |
| 2 | Dokter: jadwal R | Dokter juga boleh **memanggil pasien ke kursi** (ubah status kunjungan) | Praktik kecil sering tanpa perawat di meja depan. |
| 3 | `appointments.planned_treatment_ids` | `category` (jenis perawatan) + `reason` (teks rencana/keluhan) | Cukup untuk papan jadwal; tautan ke item rencana dilakukan saat tindakan dicatat. |
| 4 | Permukaan `V` = vestibular | Dipakai `M O D V L`. Mockup sempat memakai B/P; aplikasi mengikuti planning. | Satu standar. Cocokkan dengan formulir klinik di Fase 0. |
| 5 | Rencana dengan pilihan A/B/C (mockup) | Satu rencana = satu set item. Pilihan alternatif dibuat sebagai rencana terpisah. | Sederhana dan jelas statusnya. "Mode pasien" untuk menandatangani rencana menyusul di Fase 7 bersama odontogram visual. |
| 6 | — | Tema gelap dimatikan; warna utama teal gelap `#0D6E63` sesuai Fondasi UI mockup | Konsistensi tampilan klinik. |
| 7 | — | Tindakan punya kolom baru `recall_months` | Recall otomatis (mis. scaling 6 bulan) tanpa input manual. |

---

## 5. Data Demo

`php artisan db:seed --class=DemoSeeder` (staging) sekarang juga mengisi:
- jadwal praktik Drg. Budi & Drg. Maya
- 9 janji hari ini di Cabang Kemang, satu pasien sudah check-in di ruang tunggu
- rencana perawatan yang sudah disetujui (tambal 36, 37; cabut 18)
- 3 recall scaling

Seeder aman dijalankan ulang. Jalankan lagi di hari lain untuk mendapat janji demo hari itu.

---

## 6. Belum Terverifikasi (butuh lingkungan nyata)

- Tanda tangan di tablet/HP asli (signature pad sudah memakai pointer events + `touch-action: none`).
- Tampilan PDF persetujuan & odontogram dengan printer klinik.
- Simbol & kode odontogram, aturan DMF-T, dan isi template persetujuan **wajib ditinjau dokter gigi klinik** sebelum dipakai.
