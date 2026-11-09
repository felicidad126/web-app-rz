# Edisi inventori (branch `inventori`)

> 9 November 2026 · Disepakati dengan user: klinik hanya ingin memakai menu inventori (seperti aplikasi BHP buatan mereka), tidak selengkap aplikasi klinik di `main`.

## 1. Keputusan

| Hal | Keputusan |
|---|---|
| Cara | Branch terpisah permanen `inventori` (dari `main` 9 Nov 2026). |
| Menu | Gaya aplikasi kita: *Stok* (Barang, Permintaan, Pesanan, Mutasi & penerimaan, Opname, Kas pembelian), *Master barang*, *Organisasi*, *Pengaturan & Akses*, *Laporan*, *Bantuan*. |
| Server | pwa.helonoia.net diganti menjadi edisi inventori, database direset. |
| Barang kedaluwarsa | Tetap lewat **Ajukan pemusnahan** + persetujuan owner/admin (bukan langsung dikeluarkan). |
| Pemangkasan | Menu, halaman, widget, rute, dan izin modul klinik dihapus. Tabel & kode inti (model, service, migrasi) dibiarkan agar perbaikan stok/pembelian dari `main` mudah dipindahkan. |

## 2. Isi

- **Ada:** login + MFA, profil, pengguna, peran & hak akses, audit log, cabang (nama, alamat, telepon); master barang (obat & bahan, satuan, kategori, supplier) + Import CSV; stok lengkap (penerimaan, pemakaian manual, transfer, pemusnahan dengan persetujuan, opname, kartu stok, berita acara PDF); pembelian (permintaan → pesanan → terima sebagian → kas pembelian); dashboard stok; laporan **Stok**, **Kedaluwarsa**, **Kas pembelian** (Excel, PDF, **CSV**); panduan (Umum, Perawat, Admin, Owner) & pertanyaan umum; logo, kop cetak, PWA.
- **Dihapus dari tampilan:** pasien, jadwal & antrean, janji temu, kunjungan, papan antrean, recall, briefing pagi; pemeriksaan, rekam medis, odontogram, resep, rencana perawatan, order lab; master klinis (tindakan & tarif/BOM, ICD, kondisi odontogram, template persetujuan, paket); kasir, tagihan, paket & cicilan, tutup kasir, persetujuan void/refund; jasa dokter & gaji, target; SATUSEHAT; tenaga kesehatan, penjamin, kesiapan cabang. Field *Kode KFA*, *Harga jual*, tanda *Lab gigi* di supplier, kursi & lokasi SATUSEHAT di cabang disembunyikan.
- **Peran:** owner (semua master & pengguna, persetujuan, kas pembelian, laporan), admin (sama + memesan & menerima barang), perawat (barang masuk, pemakaian, ajukan pemusnahan, opname, minta beli, terima barang pesanan). Lihat `RolePermissionSeeder::matrix()`.
- **Pemakaian bahan** dicatat manual (tidak ada modul tindakan yang memotong stok otomatis).
- **Jadwal otomatis:** hanya backup & bersih-bersih antrean (tanpa SIP, SATUSEHAT, KPI, ringkasan harian).

Padanan menu aplikasi BHP klinik → edisi ini: Data BHP → *Master barang → Obat & bahan* / *Stok → Barang*; Expired → *Stok → Barang* filter kedaluwarsa + Ajukan pemusnahan; Riwayat stok → *Stok → Mutasi & penerimaan* + kartu stok; Restock → *Catat penerimaan* / *Terima barang*; Pemakaian → *Catat pemakaian*; Permintaan pembelian → *Stok → Permintaan*; Pembelian → *Stok → Pesanan*; Kas → *Stok → Kas pembelian*; Laporan CSV → *Laporan* (Download CSV); Users/Activity log → *Pengaturan & Akses*.

## 3. Mengganti demo pwa.helonoia.net menjadi edisi inventori (reset database)

⚠️ Menghapus **semua** data di database server (data demo lama, termasuk akun owner yang dibuat sendiri). Jangan dijalankan bila sudah ada data sungguhan.

```bash
sudo REPO_BRANCH=inventori bash /var/www/dental-pms/deploy/update.sh
```

```bash
cd /var/www/dental-pms && sudo -u www-data php artisan migrate:fresh --force
```

```bash
cd /var/www/dental-pms && sudo -u www-data php artisan db:seed --force && sudo -u www-data php artisan db:seed --class=DemoSeeder --force
```

```bash
cd /var/www/dental-pms && sudo -u www-data php artisan clinic:create-owner
```

```bash
cd /var/www/dental-pms && sudo -u www-data php artisan optimize:clear && sudo -u www-data php artisan optimize && sudo -u www-data php artisan filament:optimize
```

Akun demo: `owner@demo.test`, `admin@demo.test`, `perawat@demo.test` (Pondok Ranggon), `perawat2@demo.test` (Cabang contoh); password demo tetap di `/root/dental-pms-credentials.txt`. Semua akun mengatur MFA saat login pertama.

Untuk pemakaian sungguhan nanti: instal di server terpisah dengan `DEMO_DATA=n` (provision.sh sekarang mengambil branch `inventori`), import data barang & supplier dari aplikasi lama (`docs/13-pembelian.md` §5), lalu opname awal per cabang.

## 4. Memindahkan perbaikan dari `main`

Perbaikan stok/pembelian/tampilan yang dibuat di `main` tidak otomatis masuk ke `inventori`. Buat branch dari `inventori`, `git cherry-pick <commit>`, selesaikan bentrok (file modul klinik yang dihapus di sini tetap dihapus), lalu PR ke `inventori`.

## 5. Verifikasi

`composer check` di branch ini: Pint, Larastan (0 error), Pest (tes modul klinik dihapus dari branch ini; tetap ada di `main`). `AccessControlTest` memastikan halaman modul klinik mengembalikan 404 dan izin tiga peran sesuai.
