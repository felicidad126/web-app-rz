# Fase 7: Odontogram Visual, OHI-S, Mobile, Migrasi & Rollout — Catatan Implementasi

> Status: **selesai dari sisi kode** · 6 November 2026 · **DoD menunggu rollout di klinik** (semua cabang aktif + 2 minggu tanpa bug kritis, §7)
> Acuan: [Planning v2 Fase 7](./02-dental-pms-planning-v2.md#fase-7-polish-odontogram-visual--rollout-minggu-1516) · [Review data klinik §6 (sheet pasien)](./08-review-konsep-stok-dan-jasa-dokter.md) · mockup v2 "Odontogram" & "Mobile dokter"

---

## 1. Yang Sudah Dibangun

| Checklist Fase 7 | Status | Di aplikasi |
|---|---|---|
| Odontogram SVG interaktif (Alpine.js) di atas data yang ada | ✅ | Tab **Odontogram** di kunjungan dan **Pasien → Odontogram**. Peta 32 gigi tetap + 20 gigi sulung, ketuk gigi lalu ketuk permukaan, simpan tanpa membuka form. Tabel riwayat dan cetak PDF tetap ada. PDF memakai gambar SVG yang sama (§2). |
| DMF-T otomatis + form OHI-S | ✅ | DMF-T/def-t dihitung dari kode odontogram (sudah sejak Fase 2) dan kini diperbarui langsung setelah menyimpan. Bagian baru **Pemeriksaan intraoral & OHI-S** di form pemeriksaan: oklusi, torus, palatum, diastema, gigi anomali, dan skor debris/kalkulus enam gigi indeks (§3). |
| Optimasi mobile/tablet, uji Android & iOS | ✅ kode · ⏳ perangkat | Audit 5 peran × 2 perangkat (68 halaman). Semua halaman 200, tidak ada scroll horizontal, tidak ada error JavaScript. Area ketuk aksi tabel diperbesar khusus layar sentuh. iOS Safari **belum** diuji di perangkat asli (§4). |
| Migrasi data pasien lama | ✅ | `php artisan patients:migrate-legacy <berkas>`: uji coba dulu, laporan masalah, lalu `--commit` (§5). Sudah diuji coba (tanpa menyimpan) dengan workbook klinik. |
| Pelatihan per role + panduan singkat | ✅ panduan · ⏳ pelatihan | Menu **Panduan**: bagian umum + peran pengguna. Owner/admin melihat semua. Isi di `resources/guides/*.md`. Rencana sesi pelatihan di §6. |
| Rollout ke cabang lain | ✅ alat · ⏳ pelaksanaan | **Organisasi → Kesiapan cabang** dan `php artisan clinic:branch-readiness`: daftar periksa per cabang (§7). |

---

## 2. Odontogram Visual

- **Tampilan.** Setiap gigi punya lima bagian (atas, bawah, kiri, kanan, tengah). Bagian dipetakan ke permukaan FDI sesuai rahang dan kuadran:
  - tengah = **O** (oklusal/insisal);
  - rahang atas: atas = **V**, bawah = **L**; rahang bawah: atas = **L**, bawah = **V**;
  - kuadran 1/4/5/8: kanan = **M**; kuadran 2/3/6/7: kiri = **M**.
- **Warna.** Status terkini memakai warna kode. Rencana diberi isian merah muda dengan bingkai putus-putus. Yang dikerjakan **di kunjungan ini** berwarna biru. Gigi hilang (✕), sisa akar, dan mahkota/prostetik (cincin) diberi simbol. Legenda dan DMF-T ada di bawah peta.
- **Mencatat.**
  1. Ketukan pertama memilih gigi (riwayat gigi tampil di panel).
  2. Pilih jenis catatan: **Kondisi saat ini**, **Rencana**, atau **Dikerjakan**. "Dikerjakan" hanya ada di kunjungan.
  3. Pilih kode, ketuk permukaan, lalu **Simpan catatan**.
  - Satu baris dibuat per permukaan. Kode seluruh gigi (gigi hilang, mahkota, PSA, dll.) disimpan tanpa permukaan.
  - Catatan lama tidak pernah ditimpa.
- **Penguncian.** Setelah rekam medis ditandatangani, peta hanya bisa dilihat. Penyimpanan juga ditolak di server (`LockedWithMedicalRecord`), bukan hanya disembunyikan di layar. Tanpa izin `Update:MedicalRecord` (mis. owner), peta juga hanya bisa dilihat.
- **Halaman pasien** mencatat **kondisi awal** (tanpa kunjungan), mis. untuk pasien pindahan.
- **Kode warna** amalgam, komposit, dan karies disesuaikan dengan mockup. Migrasi hanya mengubah warna yang masih bawaan.

Kode: `App\Services\Clinic\OdontogramVisual` (data & SVG), trait `App\Filament\Clinic\Concerns\EditsOdontogramChart` (simpan), view `resources/views/clinic/odontogram-chart.blade.php`.

---

## 3. Pemeriksaan Intraoral & OHI-S

- Tabel baru `oral_examinations` (satu per kunjungan). Ikut terkunci saat rekam medis ditandatangani. Baris hanya dibuat bila ada isian.
- **OHI-S (Greene & Vermillion).** Gigi indeks: 16 bukal, 11 labial, 26 bukal, 36 lingual, 31 labial, 46 lingual. Skor 0–3.
  - DI-S = rata-rata skor debris yang diisi; CI-S = rata-rata skor kalkulus yang diisi.
  - OHI-S = DI-S + CI-S.
  - Gigi indeks yang hilang dikosongkan dan tidak ikut dihitung.
  - Kategori: **Baik** ≤ 1,2 · **Sedang** 1,3–3,0 · **Buruk** > 3,0.
- Ringkasan OHI-S dan DMF-T tampil langsung di form saat skor diubah. Pemeriksaan terakhir juga muncul di halaman odontogram pasien dan di PDF odontogram.
- Rumus hanya ada di `App\Services\Clinic\OralHygiene`.

---

## 4. Mobile & Tablet

**Audit otomatis.** Playwright memakai profil Pixel 7 (Android Chrome) dan iPad gen 7. Lima peran (owner, dokter, resepsionis, kasir, perawat) membuka halaman utama masing-masing, total 68 halaman. Yang diperiksa:
- status 200 untuk semua halaman;
- lebar dokumen tidak melebihi layar (0 halaman dengan scroll horizontal);
- tidak ada error JavaScript;
- elemen sentuh < 28 px.

**Perbaikan.** Tautan aksi tabel (Lihat/Ubah), tombol urut kolom, dan tombol hapus kecil hanya setinggi ±20 px. Area ketuknya diperluas menjadi ±44 px lewat `resources/views/components/touch-targets.blade.php`:
- hanya berlaku untuk `pointer: coarse` (layar sentuh); tampilan desktop tidak berubah;
- sudah diuji: mengetuk 9 px di atas tautan "Lihat" di Pixel 7 membuka halaman pasien.

**Odontogram di HP/tablet.** Tombol permukaan 48 px. Panel pindah ke bawah peta di layar < 1100 px. Sudah dicoba di iPad dan Pixel 7: simpan tiga permukaan, gigi hilang, notifikasi, dan tabel ikut terbarui.

**Belum:** uji di **iPhone/iPad asli dengan Safari**. Lingkungan pengembangan hanya punya Chromium, jadi iOS baru diuji lewat emulasi ukuran layar. Daftar uji di perangkat asli (±30 menit per perangkat):
1. Login + MFA.
2. Pasang ke layar utama.
3. Jadwal & antrean: geser papan, tambah janji.
4. Pemeriksaan: isi SOAP, OHI-S, odontogram (ketuk gigi/permukaan), tanda tangan dengan password.
5. Persetujuan tindakan: tanda tangan jari.
6. Kasir: terima pembayaran, cetak struk.
7. Putar layar (portrait/landscape).

---

## 5. Migrasi Data Pasien Lama

```bash
# 1. Uji coba (tidak menyimpan apa pun) + laporan masalah
php artisan patients:migrate-legacy /path/data-pasien.xlsx --branch=KMG
# 2. Setelah laporan diperiksa dan berkas dirapikan
php artisan patients:migrate-legacy /path/data-pasien.xlsx --branch=KMG --commit
```

**Sebelum migrasi**, isi `CLINIC_RM_PREFIX=MR` di `.env` bila No. RM lama berpola `MR000123`. Urutan RM otomatis dimajukan melewati nomor lama terbesar, jadi pasien baru tidak mendapat nomor yang sudah terpakai. Ini juga berlaku untuk import CSV dan RM yang diisi manual.

**Yang dikerjakan perintah:**
- **Berkas & kolom.** Membaca `.xlsx` atau `.csv` (pemisah `,`/`;`/tab dikenali otomatis). Sheet dan judul kolom dikenali sendiri ("nomor Mr", "nomor KTP", "Nama Pasien", "Umur", "Jenis kelamin", "Alamat", "No HP", "Tanggal", "Perawatan", dll.). Pilih sheet lain dengan `--sheet=`.
- **Baris.** Baris judul bulan dilewati. Baris lanjutan tanpa nama (tindakan tambahan) digabung ke pasien di atasnya. Baris dengan No. RM sama digabung menjadi satu pasien.
- **NIK.** NIK yang tersimpan sebagai angka di Excel (digit ke-16 hilang), berformat ilmiah, bukan 16 digit, atau berpola tidak valid **tidak diisi** dan dilaporkan agar dikumpulkan ulang. NIK yang sudah dipakai pasien lain → baris ditolak (periksa apakah orang yang sama).
- **Tanggal lahir.** Urutan sumber:
  1. kolom tanggal lahir;
  2. tanggal yang tertanam di NIK (dicocokkan dengan umur);
  3. **perkiraan dari umur** saat kunjungan (dicatat di catatan pasien dan laporan).
  - Tanpa tanggal lahir dan umur → ditolak.
- **Format tanggal.** Dibaca dalam banyak bentuk: "Selasa, 22 April 2025", "Rabu,07/05/2025", "Kamis,03-Juli-2025", "Minggu/26/10/2025", sel tanggal Excel.
- **HP.** Dirapikan ke awalan 0 ("+62 813…" / "813…" → "0813…").
- **Pasien yang sudah ada** (No. RM sama) hanya diisi kolom yang masih kosong. Data di aplikasi tidak ditimpa. Perintah aman dijalankan ulang.
- **Riwayat kunjungan lama** (rentang tanggal & daftar perawatan) disimpan sebagai **catatan pasien** bertanda `[Data sistem lama]`, **bukan** rekam medis. Riwayat ini tidak ikut terkirim ke SATUSEHAT.
- **Laporan masalah** disimpan di `storage/app/private/migrasi/` dan hanya memuat nomor baris, No. RM, dan masalah (tanpa nama/NIK). Hapus setelah selesai.

**Hasil uji coba dengan workbook klinik** (sheet "Data dummy pasien", tidak disimpan):

| | Jumlah |
|---|---|
| Baris berisi data | 98 |
| Judul bulan dilewati | 6 |
| Baris digabung (lanjutan / RM sama) | 35 |
| Pasien yang akan dibuat | 56 |
| Ditolak (umur & tanggal lahir kosong) | 1 |
| NIK rusak karena format angka Excel | 28 |
| Tanggal lahir diperkirakan dari umur | 54 |
| Umur tidak cocok dengan NIK | 1 |

Artinya hampir semua pasien hasil migrasi perlu dilengkapi **NIK** dan **tanggal lahir** saat datang. Panduan resepsionis menjelaskannya. Data pasien dari workbook tidak disalin ke repositori.

Import CSV di **Data Pasien → Import CSV** tetap ada untuk berkas yang sudah rapi. Kini import CSV juga memakai perapih tanggal, HP, dan No. RM yang sama.

---

## 6. Panduan & Pelatihan

Menu **Panduan** (semua pengguna) berisi:
- bagian **Umum**: login & MFA, pasang di HP, memilih cabang, melapor masalah;
- bagian per peran: resepsionis, dokter, perawat, kasir, admin, owner.

Isinya berkas Markdown di `resources/guides/`, jadi klinik bisa mengubah kata-kata tanpa mengubah kode.

Usulan sesi pelatihan (di staging dengan data demo, `php artisan db:seed --class=DemoSeeder`):

| Sesi | Peserta | Durasi | Latihan |
|---|---|---|---|
| 1. Dasar | Semua | 30 mnt | Login + MFA, pasang di HP, menu Panduan, memilih cabang |
| 2. Depan | Resepsionis | 60 mnt | Pasien baru, lengkapi pasien migrasi, janji temu, check-in, walk-in, recall, kirim ulang SATUSEHAT |
| 3. Klinis | Dokter & perawat | 90 mnt | Panggil ke kursi, SOAP, diagnosis/tindakan, OHI-S, odontogram visual, resep, persetujuan, tanda tangan, addendum, order lab |
| 4. Kasir | Kasir | 60 mnt | Tagihan, pembayaran, struk, paket & cicilan, deposit, void/refund, tutup kasir |
| 5. Stok | Perawat & admin | 45 mnt | Penerimaan, pemakaian, pemusnahan, opname + persetujuan |
| 6. Manajemen | Owner & admin | 60 mnt | Briefing pagi, laporan, target, rekap jasa & slip, kesiapan cabang, persetujuan |

---

## 7. Rollout Cabang

**Organisasi → Kesiapan cabang** (owner/admin, izin `Update:Branch`) menampilkan daftar periksa per cabang. Tiap butir yang belum terpenuhi punya tautan **Atur** ke halaman perbaikannya. Versi konsol: `php artisan clinic:branch-readiness [KODE]` (exit 1 bila ada butir wajib yang kurang).

| Butir | Tingkat |
|---|---|
| Data cabang (alamat, telepon) & aktif | Wajib |
| Kursi / dental unit aktif | Wajib |
| Akun aktif: dokter, resepsionis, kasir | Wajib |
| Akun perawat | Disarankan |
| MFA staf sudah diatur | Disarankan |
| Jadwal praktik dokter | Wajib |
| SIP dokter terjadwal masih berlaku | Wajib |
| Tindakan & tarif aktif | Wajib |
| Aturan jasa dokter aktif | Wajib |
| Stok awal (opname disetujui) | Disarankan |
| Target produksi bulan ini | Disarankan |
| SATUSEHAT: Location cabang & IHS dokter (bila diaktifkan) | Wajib |

**Langkah per cabang:**
1. **H-7:** lengkapi butir wajib, buat akun staf, lalu jalankan migrasi pasien (uji coba → `--commit`).
2. **H-3:** pelatihan (§6), lalu opname awal stok.
3. **H-1:** halaman Kesiapan cabang = "Siap". Uji satu alur penuh: janji → periksa → tanda tangan → bayar → struk.
4. **H:** go-live. Admin mendampingi di meja depan dan kasir.
5. **H+1 s/d H+14:** catat masalah harian.

Bug kritis = data salah/hilang, tidak bisa melayani pasien, tidak bisa menerima pembayaran, atau data bocor ke peran yang tidak berhak. DoD tercapai bila semua cabang aktif dan tidak ada bug kritis selama 2 minggu.

---

## 8. Keputusan & Penyimpangan

- **Data demo:** pasien yang sedang menunggu di papan hari ini diberi odontogram kondisi awal dan rencana. Dengan begitu, odontogram visual langsung terisi saat pelatihan.
- **Riwayat kunjungan lama tidak dijadikan rekam medis.** Rekam medis bertanda tangan akan ikut terkirim ke SATUSEHAT dan terkunci, jadi riwayat lama cukup disimpan sebagai catatan pasien (sesuai review §6).
- **NIK rusak dikosongkan**, tidak "diperbaiki". Digit yang hilang tidak bisa ditebak, dan NIK yang salah akan ditolak SATUSEHAT atau tertaut ke orang lain.
- **Perkiraan tanggal lahir** = tanggal kunjungan − umur − 6 bulan (titik tengah rentang yang mungkin). Selalu ditandai sebagai perkiraan.
- **Panduan berupa Markdown di repositori**, bukan CMS. Mudah diubah dan ikut ter-review. Tidak butuh izin Shield baru (semua pengguna yang login boleh membuka).
- **Target sentuh** diperbesar dengan CSS kecil lewat render hook, bukan tema Filament baru, sehingga tidak perlu build aset tambahan.

---

## 9. Verifikasi

- `composer check` lulus: Pint, Larastan level 5, Pest **272 tes**. Juga dijalankan di MySQL 8.
- `OdontogramVisualTest`:
  - pemetaan permukaan per kuadran (termasuk gigi sulung);
  - data visual (status, rencana, gigi hilang, mahkota, dikerjakan di kunjungan ini, DMF-T);
  - simpan dari kunjungan (satu baris per permukaan, kode seluruh gigi tanpa permukaan);
  - tolak gigi/kode/permukaan tidak valid; tolak setelah ditandatangani dan tanpa izin ubah;
  - halaman pasien (kondisi awal, "dikerjakan" ditolak) + PDF;
  - rumus OHI-S; form pemeriksaan menyimpan OHI-S lalu terkunci setelah tanda tangan.
- `LegacyPatientMigrationTest` (data fiktif):
  - perapih nilai (tanggal, NIK, HP, umur, RM);
  - uji coba tidak menyimpan dan laporan tanpa nama/NIK;
  - commit: gabung baris, NIK rusak dikosongkan, tanggal lahir dari NIK/umur, melengkapi pasien lama tanpa menimpa, urutan RM maju, dijalankan ulang tanpa duplikat;
  - CSV titik koma + perintah artisan.
- `RolloutTest`: cabang kosong belum siap; cabang lengkap siap; SIP lewat → belum siap; halaman & perintah; panduan sesuai peran.
- `AccessControlTest`: 6 kasus baru. `PwaTest`: CSS target sentuh terpasang.
- Dicek di browser (Playwright):
  - odontogram visual di desktop, iPad, dan Pixel 7;
  - form OHI-S;
  - halaman Panduan & Kesiapan cabang di HP/desktop;
  - audit mobile 68 halaman (§4).

---

## 10. Menunggu dari Klinik

1. Uji di **iPhone/iPad asli** dengan Safari (daftar di §4) dan di tablet Android yang dipakai dokter.
2. **Konfirmasi kode & warna odontogram** dengan dokter gigi klinik (masih draf sejak Fase 1).
3. Berkas data pasien lama yang **final** (NIK diformat sebagai Teks bila memungkinkan), lalu migrasi di server.
4. Jadwal pelatihan dan tanggal go-live per cabang.
5. Keputusan Fase 6 yang masih terbuka: persentase final per jenis jasa, nominal uang hadir/on call, insentif asisten, Inova Care, SMTP untuk email ringkasan.
