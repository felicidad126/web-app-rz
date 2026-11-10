# Perapian UI/UX — Catatan & Konvensi

> 7 November 2026 · Acuan: mockup v2 "Fondasi UI" (artifact *Mockup Dental PMS*) · Status terakhir proyek tetap di [docs/11](./11-fase-7-implementasi.md)

Semua halaman panel (79 halaman, 6 peran, desktop & HP) difoto, diaudit per segmen terhadap prinsip Fondasi UI, lalu dirapikan. Dokumen ini mencatat **aturan yang sekarang dipakai** supaya halaman baru langsung seragam.

---

## 0. Edisi inventori: Konsep UI inventori (9–10 Nov 2026)

Di branch `inventori`, bagian ini menggantikan warna teal, font IBM Plex Sans, dan bilah atas dari §1–§3. Acuan: artifact *Konsep UI inventori* (Dashboard, Stok · Barang, HP, Pilihan font), dari referensi CRM Dashboard + DINEOS yang dipilih klinik. Diterapkan bertahap: tahap 1 tema & tata letak, tahap 2 Dashboard, tahap 3 menu bawah di HP.

| Bagian | Aturan |
| --- | --- |
| Warna utama | Ungu `#6C5DD3` (primary 600, teks putih 5,07:1) hanya untuk tombol utama & posisi aktif; 700 `#5B4BC4` untuk hover & teks menu aktif; 50 `#F1EFFF` sorotan menu/tab aktif. |
| Abu-abu | Navy: 50 `#F6F6FB` latar halaman, 500 `#6B6C80` teks sekunder (AA di putih & latar), 950 `#11142D` teks utama & sub-menu aktif. Palet ditulis manual di `AdminPanelProvider` (`VIOLET`, `NAVY_GRAY`). |
| Warna status | Tetap: kuning perlu tindakan, hijau selesai, merah lewat ED & gagal. Warna status tidak dipakai untuk hiasan. |
| Font | Inter (teks, menu, tabel, form; `ss04` agar I/l/1 berbeda tanpa nol bergaris), Plus Jakarta Sans (judul halaman, judul kartu, angka besar), IBM Plex Mono (kode). Di-host di `public/fonts/{inter,jakarta,plex}` dengan lisensi OFL; dipangkas ke huruf Latin. Hasil riset & pengukuran: artboard *Pilihan font*. |
| Tata letak | Tanpa bilah atas (`->topbar(false)`): logo + tombol ciutkan, kotak Cari, menu, notifikasi, dan menu pengguna ada di sidebar putih (kartu mengambang setinggi layar di desktop). Menu atas tanpa judul kelompok: Dashboard, Stok, Laporan; lalu *Master barang*, *Organisasi & akses*, *Bantuan*. Kelompok selalu terbuka. |
| Bentuk | Kartu putih bersudut 24 px + bayangan lembut (`--klinik-shadow-card`), isian bersudut 14 px, tombol/tab/badge berbentuk pil. Sub-menu halaman (mis. Stok) aktif = pil navy; tab di dalam halaman aktif = pil ungu muda. |

Semua aturan ada di `public/css/klinik.css` (bagian atas) dan `AdminPanelProvider`. §1–§3 di bawah tetap berlaku untuk hal yang tidak disebut di sini (uang rata kanan, kode mono, kolom sekunder disembunyikan di HP, dst.).

---

## 1. Prinsip (dari mockup)

1. **Konteks pasien selalu terlihat.** Di layar klinis & kasir, banner pasien (nama, No. RM, alergi, penjamin, saldo terutang) ada di posisi yang sama.
2. **Warna = arti.** Teal `#0D6E63` hanya untuk aksi utama & posisi aktif. Kuning = perlu tindakan (jatuh tempo, belum lunas, menunggu persetujuan). Hijau = selesai/lunas. Merah hanya untuk peringatan medis & kegagalan (alergi, gagal kirim, selisih kas). Sisanya abu-abu. Pengecualian: odontogram ikut konvensi klinis.
3. **Tabel lebih dulu, kartu seperlunya.**
4. **Satu tombol teal per layar.** Aksi lain abu-abu; hapus/arsip netral, merah hanya di jendela konfirmasi.
5. **Angka rata kanan, kode pakai huruf mono.** Uang di tabel tanpa "Rp".
6. **Target sentuh ≥ 44 px di tablet/HP**, desktop boleh lebih padat (baris ±44 px).
7. **Status janji/kunjungan = titik + kata**: Terjadwal, Dikonfirmasi, Datang, Di kursi, Selesai, Tidak datang.
8. **Kosakata seragam:** Briefing pagi, Tidak datang, Datang langsung, Penerimaan rencana, Bulan berjalan, Saldo terutang. Judul memakai huruf kalimat ("Order lab", bukan "Order Lab").
9. **Bahasa:** Bahasa Indonesia, tetapi istilah yang di klinik lazim dipakai dalam bahasa Inggris tidak dipaksakan diterjemahkan (keputusan 7 Nov 2026):

   | Bukan | Pakai |
   |---|---|
   | Dasbor | Dashboard |
   | Kata sandi | Password |
   | Masuk / Keluar (akun) | Login / Logout |
   | Atur ulang | Reset |
   | Unggah / Unduh | Upload / Download |
   | Impor / Ekspor | Import / Export |
   | Autentikasi dua faktor (2FA) | MFA (verifikasi dua langkah) |

   Terjemahan bawaan Filament ditimpa di `lang/vendor/<paket>/id/` (hanya kunci yang berubah). Label lain yang sudah wajar dalam Bahasa Indonesia (Pasien, Kunjungan, Tagihan, Simpan, Ubah, Hapus, Laporan…) tetap. "Masuk/Keluar" untuk stok & kas (barang masuk, kas keluar) juga tetap.

---

## 2. Cara menerapkannya di kode

| Kebutuhan | Pakai |
|---|---|
| Warna, font | Sudah di `AdminPanelProvider`: palet teal manual (600 = `#0D6E63`), abu-abu zinc, IBM Plex Sans/Mono di-host sendiri (`public/fonts/plex`, lisensi OFL). |
| CSS tambahan | Hanya `public/css/klinik.css` (file statis, tanpa build aset, dimuat lewat `<x-panel-styles />`). |
| Kode (No. RM, kunjungan, tagihan, ICD, gigi, SKU) | Kolom: `->fontFamily(FontFamily::Mono)`. Blade/description: `<span class="klinik-mono">`. Jangan menulis `ui-monospace` langsung. |
| Uang di tabel | `->formatStateUsing(fn ($s) => Money::format((int) $s, false))->alignEnd()`; jangan `->money()`. |
| Isian rupiah | `MoneyInput::make('field')` (Rp + pemisah ribuan saat mengetik, tersimpan bilangan bulat). |
| Status janji/kunjungan | Kolom badge + `->extraAttributes(['class' => 'klinik-status'])` → titik + kata. |
| Badge jenis/kategori | `->color('gray')`; warna hanya untuk status yang berarti. |
| Kolom sekunder di HP | `->visibleFrom('md')` (lebar konten = layar − sidebar ±300 px). Tabel Blade: kelas `klinik-hide-sm`. |
| Aksi baris | Ubah & hapus sudah abu-abu secara bawaan; aksi tambahan masuk `ActionGroup`. |
| Judul resource | Trait `SentenceCaseLabels` (sudah di semua resource). |
| Tanggal & angka | Bawaan tabel/infolist: `d/m/Y`, `d/m/Y H:i`, angka lokal `id`. |
| Tabel kosong | Ikon kotak masuk (bukan tanda silang); beri `emptyStateDescription` yang menjelaskan kapan data muncul. |

---

## 3. Yang diubah

### Global
- Tombol utama kini benar-benar teal klinik (sebelumnya toska terang karena `Color::hex()` hanya mengambil hue).
- IBM Plex Sans & Mono, di-host sendiri (tanpa CDN, ikut tersimpan service worker).
- Judul halaman 22/600, label kolom 12/500 abu-abu, angka tabular, baris tabel ±44 px di desktop.
- Kartu "Selamat datang / Keluar" dihapus dari dasbor; Panduan pindah ke grup **Bantuan**.
- Warna status: sebagian/final = kuning; void, pembayaran aktif, jenis jasa, transfer stok = netral.
- Banner pasien menampilkan penjamin, saldo terutang (kuning bila ada), dan deposit.
- Warna tema PWA, avatar, dan halaman offline = `#0D6E63`.

- Bawaan tabel: tanggal `d/m/Y`, angka lokal `id`, ikon tabel kosong netral, badge & tombol reset filter abu-abu. Tombol Ubah/Hapus netral; merah hanya di jendela konfirmasi hapus.
- Judul huruf kalimat ("Buat janji temu", "Ubah order lab"); label menu "Data pasien", "Kondisi odontogram", "Peran & hak akses"; grup "Master barang" (sebelumnya sama dengan nama menunya, "Obat & Bahan").
- Status kunjungan "Menunggu" → **Datang** (enam kata status seragam).

### Harian, Pasien, Klinis
- **Dasbor:** kartu statistik 2 per baris di HP; kartu SATUSEHAT disembunyikan selama pengiriman belum aktif & belum pernah ada kiriman; grafik produksi selebar halaman, batang abu-abu; tabel cabang/dokter bisa digeser dengan kolom pertama tetap; "Pasien saya hari ini" memakai titik status & No. RM mono; order lab terlambat kuning (bukan merah).
- **Jadwal & antrean:** satu tombol teal ("Buat janji"); kartu 30 menit tidak lagi memotong status; di HP panel antrean di atas papan.
- **Briefing pagi:** slot kosong satu baris per slot; Kemarin & Besok memakai lebar penuh.
- **Pemeriksaan:** banner pasien di atas tab (tetap terlihat di tab Odontogram/Lampiran); "Simpan draf" abu-abu; tanda vital 2 per baris di HP; OHI-S 6 kolom dan tertutup sampai ada isian.
- **Janji temu / Kunjungan:** titik status, kode mono, kolom sekunder disembunyikan di HP, pesan tabel kosong yang jelas. **Perbaikan bug:** daftar janji temu kosong di SQLite karena filter tanggal membandingkan "2026-11-06 00:00:00" dengan tanggal; sekarang dibandingkan per tanggal.
- **Pasien:** No. RM mono, klik baris membuka pasien; halaman pasien berjudul nama pasien dengan banner & tab Riwayat kunjungan lebih dulu; halaman ubah tanpa tabel relasi; form pendaftaran menyembunyikan isian RM/IHS yang kosong.
- **Recall, Rencana perawatan, Order lab:** aksi baris abu-abu, nominal tanpa "Rp", banner pasien di detail, dokter default = dokter yang login, biaya lab & estimasi memakai `MoneyInput`.

### Keuangan, Jasa & Gaji, Laporan
- **Kasir & Tagihan:** tombol baris abu-abu; kode mono; total tagihan tidak lagi tersembunyi di tabel geser (HP); kolom Gigi/Harga/Diskon disembunyikan di HP.
- **Tutup kasir:** judul = menu; "✓ Cocok" abu-abu, merah hanya untuk selisih; detail kasir/waktu di subjudul.
- **Paket & cicilan:** judul = nama paket; satu tombol "Terima" pada cicilan berikutnya (hanya untuk yang berhak); jatuh tempo kuning "lewat N hari"; form "Jual paket" tanpa "Buat & buat lainnya".
- **Persetujuan:** satu pesan kosong, bukan tiga kartu kosong.
- **Rekap/Rincian jasa, Kehadiran, Slip & Komponen gaji, Target:** muat di 1440 px; filter periode di atas tabel; baris satu tinggi; label repeater berbahasa Indonesia; slip final tampil sebagai tabel baca-saja; "Ubah" disembunyikan untuk kehadiran yang sudah direkap; nominal "5.000.000" (bukan "Rp 0,00").
- **Laporan:** filter satu baris di desktop; kolom pertama tetap saat tabel digeser; "Diagnosis terbanyak".

### Master, Stok, Organisasi, Pengaturan
- **Master (Cabang, Nakes, Penjamin, Tindakan, ICD, Kondisi odontogram, Obat & bahan, Supplier):** kode mono, badge jenis abu-abu, angka & harga rata kanan tanpa "Rp", tabel Tindakan muat di 1440 px, ICD menampilkan nama Indonesia, hapus/pulihkan di menu "Lainnya", tombol tambah relasi abu-abu, isian rupiah memakai `MoneyInput`.
- **Stok:** chip aktif gelap (bukan teal); "Mulai opname" jadi aksi utama di tab Opname; detail opname/mutasi tanpa baris kuning dan muat di HP.
- **Pengguna & Audit log:** label Indonesia ("Alamat email", "Password"); Reset MFA di menu; nilai audit diterjemahkan (Dibuat/Diubah/Dilihat, Pasien/Kunjungan/…).
- **SATUSEHAT, Kesiapan cabang, Panduan, Profil:** teks banner sesuai hak akses; butir yang belum terpenuhi di atas; lebar teks panduan dibatasi; profil dalam kartu "Akun" dua kolom.

### Belum dikerjakan (sengaja)
- Isian rupiah di form tagihan kasir & hitung pecahan saat tutup kasir tidak diberi pemisah ribuan: totalnya dihitung langsung dari isian mentah, masker berisiko merusak total.
- Hapus belum diganti istilah "Arsipkan" di semua tempat.
- Nama aplikasi: **East Family Dental Care** (ikon HP: **East Dental**), keputusan 12 Nov 2026. Hanya dari `APP_NAME` / `APP_SHORT_NAME`; jangan menulis nama langsung di kode atau view.
- Alamat klinik ada di data **cabang** (Organisasi → Cabang), tampil di struk, nota, dan berita acara di bawah nama aplikasi. Data demo: cabang utama **Pondok Ranggon** (Jl. Pd. Ranggon, RT.2/RW.3, Kec. Cipayung, Jakarta Timur 13860), cabang kedua **Cabang contoh** (fiktif, untuk fitur multi-cabang). Tanpa pilihan, owner/admin membuka cabang yang pertama didaftarkan (bukan urutan abjad).
- **Logo klinik** (rumah + gigi, garis #5A5149 di krem #FEF8E8), dari gambar klinik lalu divektorkan: `public/icons/logo.svg` (header & login, bersama nama aplikasi lewat `resources/views/filament/brand-logo.blade.php`), `icons/icon*.png`/`icon.svg` (ikon PWA & halaman offline), `favicon.ico`/`favicon.png` (versi garis tebal agar terbaca di 16 px). Warna UI tetap teal Fondasi UI.
- **Lebar konten penuh** (`maxContentWidth(Width::Full)`): tabel & dashboard memakai seluruh layar lebar; semua rata kiri (tidak ada kolom di tengah). Form buat/ubah dibatasi ±80rem di layar ≥ 1536 px dan Profil 56rem (`klinik.css`), tetap rata kiri.
- **Cetak halaman panel** (tombol Cetak di Briefing pagi atau Ctrl+P di halaman mana pun): `@media print` di `klinik.css` menyembunyikan sidebar, topbar, tombol, filter, paginasi; isi selebar kertas A4 (margin 12 mm). Elemen yang tidak perlu dicetak: kelas `klinik-no-print`. Kop klinik (logo, nama, cabang & alamat, tanggal cetak, nama pencetak) muncul di atas setiap hasil cetak (`resources/views/filament/print-header.blade.php`, render hook `CONTENT_START`), disembunyikan di layar.
- **Panduan** = pusat bantuan (mockup disepakati 7 Nov 2026): judul *Butuh bantuan?* + kotak cari yang langsung menyaring topik dari semua peran yang boleh dilihat, kartu per peran (peran sendiri ditandai *Peran Anda*), *Pertanyaan umum* (buka-tutup, isi `resources/guides/faq.md`). *Lihat panduan* membuka halaman peran (`/panduan?peran=kasir`): daftar topik di kiri, isi di kanan. Topik dibaca `App\Support\GuideContent` dari `## Judul` atau paragraf `**Judul** — …`.
- Input tanggal di Jadwal masih memakai format bawaan browser.

---

## 4. Verifikasi

- `composer check` lulus: Pint, Larastan level 5 (0 error), Pest 272 tes. Tes halaman klinis & kasir juga lulus di MySQL 8 (filter tanggal janji temu/kunjungan berubah).
- Foto sesudah: 79 halaman × desktop 1440 px & Pixel 7 untuk 6 peran (158 foto) — semua status 200, tidak ada scroll horizontal, tidak ada error JavaScript. Perbandingan sebelum/sesudah 14 halaman utama dikirim ke owner/tim.
- Tiga subagent mengaudit lalu merapikan segmen masing-masing dengan pembagian file yang tidak tumpang tindih; hasil digabung dan dicek ulang di sini.
- Bug yang ditemukan saat merapikan: (1) daftar janji temu kosong di SQLite (filter tanggal), (2) tombol hapus di baris tabel masih merah karena closure `modalSubmitAction` menerima aksi induk (parameter harus bernama `$action`).
