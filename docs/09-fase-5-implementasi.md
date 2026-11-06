# Fase 5: Stok, Pembelian & Lab — Catatan Implementasi

> Status: **selesai** · 6 November 2026
> Acuan: [Planning v2 §5.6 & §6.3, Fase 5](./02-dental-pms-planning-v2.md#fase-5-stok-pembelian--lab-minggu-1112) · [Review konsep stok klinik](./08-review-konsep-stok-dan-jasa-dokter.md) · tampilan mengikuti mockup v2 "Stok" dan "Order lab"

---

## 1. Yang Sudah Dibangun

| Checklist Fase 5 | Status | Di aplikasi |
|---|---|---|
| Batch + FEFO + mutasi stok + transfer antar cabang | ✅ | Stok per **batch per cabang** (no. batch, ED, harga pokok). Barang keluar otomatis diambil dari batch dengan **ED terdekat** dan melewati batch yang sudah kedaluwarsa. Semua perubahan tercatat sebagai **mutasi** (dokumen + baris per batch) dan tidak bisa diubah/dihapus. **Stok → Lainnya → Transfer ke cabang lain** memindahkan batch, ED, dan harga pokok yang sama. |
| Penerimaan barang dari supplier | ✅ | **Stok → Catat penerimaan**: supplier, no. faktur, banyak barang sekaligus, satuan beli (box/strip) dikonversi ke satuan dasar, harga per satuan beli, no. batch, ED. ED "08/2027" disimpan sebagai 31/08/2027. Master **Supplier & Lab** dan **Kategori Barang** di grup Obat & Bahan. |
| Pemotongan otomatis dari BOM & resep, dengan mutasi balik | ✅ | **Tindakan & Tarif → Bahan (BOM)**: bahan per 1× tindakan, dengan perkiraan HPP. Bahan BOM dipotong saat **rekam medis ditandatangani**; obat resep saat **resep diserahkan** di kasir; barang retail saat **tagihan terbit**. Void tagihan mengembalikan barang retail. Admin bisa **Batalkan mutasi** penerimaan/pemakaian/penyerahan obat (salah input, barang kembali). |
| Stok opname dengan approval | ✅ | **Stok → Opname**: saldo sistem dicatat saat mulai, petugas menghitung **tanpa melihat saldo sistem**, lalu mengajukan. Admin klinik menyetujui (atau mengembalikan untuk dihitung ulang); selisih menjadi mutasi **Koreksi opname**. Opname pertama sekaligus **saldo awal**. |
| Alert stok minimum & expired | ✅ | Widget **Stok & lab** di dasbor (di bawah minimum, kedaluwarsa ≤ 60 hari, stok minus, menunggu persetujuan, order lab terlambat) dan tombol filter di **Stok → Barang**. Ambang hari bisa diubah: `CLINIC_STOCK_EXPIRY_WARNING_DAYS`. |
| HPP per tindakan | ✅ | Perkiraan HPP di tab BOM (BOM × harga pokok rata-rata cabang). HPP aktual bahan dan biaya lab disimpan di baris tagihan saat terbit (`invoice_items.material_cost`, `lab_cost`), untuk margin dan jasa dokter di Fase 6. |
| Order lab + biaya lab | ✅ | **Operasional → Order Lab**: Dibuat → Dikirim ke lab → Diterima klinik → Dicek dokter → Dipasang (atau Batal). Urutan: terlambat dulu, lalu jatuh tempo terdekat. Kolom **Janji pasang** dari janji temu pasien berikutnya, dengan peringatan bila jadwalnya sebelum pekerjaan lab kembali. Dari halaman pemeriksaan: **Lainnya → Buat order lab**. |
| Test FEFO & konversi satuan | ✅ | `tests/Feature/StockLedgerTest.php` (ledger) dan `StockPagesTest.php` (halaman). Lihat §7. |

**DoD Fase 5** (stok sistem = stok fisik setelah opname pertama): opname yang disetujui membuat saldo setiap batch sama dengan hasil hitung (diuji otomatis). Tinggal dijalankan di klinik (§4).

---

## 2. Cara Kerja Stok

- **Satuan dasar.** Semua jumlah disimpan dalam satuan terkecil barang (ampul, tablet, pcs). Satuan beli lain (box, strip) diatur di **Obat & Bahan → Konversi Satuan**.
- **Harga pokok** disimpan per satuan dasar per batch, dalam rupiah bulat. Bila batch yang sama diterima lagi dengan harga berbeda, harga pokoknya menjadi rata-rata tertimbang.
- **Ledger adalah acuan.** `stock_movements` + `stock_movement_lines` mencatat setiap perubahan; `item_batches.qty_on_hand` hanya cache yang diperbarui di transaksi database yang sama. Hanya `App\Services\Stock\StockLedger` yang mengubah stok.
- **Mutasi tercatat tidak bisa diubah atau dihapus.** Koreksi lewat mutasi pembatalan atau opname.
- **Pelayanan tidak tertahan oleh stok.** Bila bahan BOM/resep habis di sistem, pemakaian tetap dicatat ke batch **"Tanpa batch"** (stok minus), muncul di peringatan **Stok minus**, dan dibereskan saat opname. Kegagalan pencatatan stok dilaporkan ke log error, tanpa menggagalkan tanda tangan rekam medis atau penerbitan tagihan.
- **Kartu stok** per barang: **Obat & Bahan → (barang) → Kartu stok**, dengan saldo cabang setelah setiap mutasi. Tab **Batch** menampilkan stok, ED, dan nilai per batch.

### Jenis mutasi

| Jenis | Nomor | Dibuat dari | Persetujuan |
|---|---|---|---|
| Penerimaan | `PNR-KMG-2611-0001` | Catat penerimaan | — |
| Pemakaian | `KLR-…` | Tanda tangan rekam medis (BOM), atau Catat pemakaian manual | — |
| Penyerahan obat | `KLR-…` | Resep diserahkan di kasir | — |
| Penjualan | `KLR-…` | Tagihan terbit (barang retail) | — |
| Transfer keluar/masuk | `TRF-…` | Transfer ke cabang lain (dua dokumen, satu per cabang) | — |
| Pemusnahan | `MSN-…` | Ajukan pemusnahan | **Ya**, admin klinik, bukan pengaju |
| Koreksi opname | `KOR-…` | Opname disetujui | **Ya**, lewat opname |
| Pembatalan | `BTL-…` | Void tagihan, atau Batalkan mutasi (admin) | — |

**Berita acara** (PDF A4, blok tanda tangan pembuat dan penyetuju) bisa dicetak dari setiap mutasi: **Cetak berita acara**. Judulnya menyesuaikan jenis: penerimaan, pemusnahan, koreksi stok, mutasi antar cabang.

---

## 3. Alur per Peran

| Peran | Yang dikerjakan |
|---|---|
| **Perawat** | Catat penerimaan, catat pemakaian di luar BOM, ajukan pemusnahan, transfer (bila ditugaskan di lebih dari satu cabang), mulai & hitung opname, buat & perbarui order lab |
| **Admin klinik** | Semua yang di atas, plus **menyetujui** pemusnahan dan opname, **membatalkan** mutasi salah input, mengatur BOM, supplier, kategori |
| **Owner** | Melihat stok, mutasi, opname, order lab; ikut bisa menyetujui |
| **Dokter** | Melihat stok & mutasi; membuat dan memperbarui order lab (mis. tahap "Dicek dokter") |
| **Resepsionis** | Melihat dan memperbarui order lab (barang datang, jadwal pasang) |
| **Kasir** | Melihat halaman Stok → Barang (cek ketersediaan barang retail) |

Persetujuan selalu **4 mata**: penyetuju harus punya izin `Approve:Stock` dan bukan orang yang mengajukan. Sesuai keputusan owner (review 6 Nov 2026, pertanyaan 6), penyetuju adalah **admin klinik**.

---

## 4. Memulai di Klinik (saldo awal)

1. Lengkapi master: **Obat & Bahan** (satuan dasar, konversi, stok minimum, kategori), **Supplier & Lab**, dan **BOM** untuk tindakan yang sering dikerjakan.
2. **Stok → Mulai opname** di tiap cabang. Lembar hitung berisi semua barang aktif. Isi no. batch, ED, harga pokok, dan jumlah fisik per batch; tambah baris bila satu barang punya beberapa batch.
3. Masukkan juga bahan yang **sudah kedaluwarsa** (sheet "Bahan Expired" klinik) dengan ED aslinya, supaya bisa dimusnahkan dengan berita acara.
4. **Ajukan untuk disetujui** → admin klinik memeriksa selisih → **Setujui & catat koreksi**. Setelah ini stok sistem = stok fisik.
5. **Ajukan pemusnahan** untuk bahan kedaluwarsa (form terisi otomatis dengan batch yang sudah lewat ED) → admin menyetujui → cetak berita acara.

Data kartu stok lama 2023 di workbook klinik tidak dipakai sebagai saldo awal (lihat review §5).

---

## 5. Order Lab

- Data: pasien, kunjungan & tindakan (opsional), dokter, lab (supplier bertanda **Lab gigi**), jenis pekerjaan (crown, bridge, gigi tiruan, retainer, aligner, veneer, night guard), spesifikasi, gigi, shade, biaya lab, janji selesai dari lab.
- Nomor `LAB-2611-0001` lewat `NumberSequence`.
- **Terlambat**: belum diterima klinik padahal tanggal janji lab sudah lewat. Ditandai merah di daftar, badge di menu, dan widget dasbor.
- **Biaya lab** order yang tidak batal ikut disimpan di baris tagihan kunjungan saat terbit, untuk margin dan jasa dokter di Fase 6.
- Tombol **Jadwalkan pemasangan** membuka form janji temu dengan pasien dan dokter terisi; **Hubungi lab** memakai nomor telepon lab.

---

## 6. Penyimpangan dari Planning & Keputusan

| # | Planning / review | Implementasi | Alasan |
|---|---|---|---|
| 1 | Pemotongan BOM "per tindakan" | Dipotong sekali saat **rekam medis ditandatangani** | Sebelum tanda tangan, tindakan masih bisa diubah. Penyesuaian per kunjungan lewat **Catat pemakaian**. |
| 2 | Alert expired ≤ 30 hari | **≤ 60 hari** (mockup), bisa diubah lewat `.env` | Mengikuti mockup v2. Isi `CLINIC_STOCK_EXPIRY_WARNING_DAYS=30` bila ingin sesuai planning. |
| 3 | `cost_price` per batch | `unit_cost` bigint rupiah per **satuan dasar**, dibulatkan | Aturan uang proyek (bigint rupiah). Selisih pembulatan per ampul/tablet < Rp1. |
| 4 | Stok tidak boleh minus | Boleh minus di batch **"Tanpa batch"** | Pelayanan pasien tidak boleh tertahan oleh data stok yang belum rapi; minus terlihat di peringatan dan dibereskan saat opname. |
| 5 | Opname | Dihitung **buta** (saldo sistem tidak tampil saat menghitung) | Mengurangi bias; selisih dilihat penyetuju. |
| 6 | Transfer dengan persetujuan cabang tujuan | Transfer langsung tercatat di kedua cabang | Cabang dalam satu manajemen; bisa ditambah tahap "diterima" bila dibutuhkan. |
| 7 | Alert | Widget, badge menu, dan filter; **belum** ada notifikasi terjadwal (email/WA) | WhatsApp di luar scope; notifikasi bisa ditambah di Fase 6 bersama dashboard per peran. |
| 8 | Review: "retur supplier" sebagai jenis transaksi | Belum ada retur sebagian. Penerimaan yang dikembalikan **seluruhnya** dibatalkan lewat **Batalkan mutasi** | Kasus jarang; retur sebagian bisa ditambah bila klinik membutuhkannya. |
| 9 | "Pembelian" (judul fase) | Pencatatan **penerimaan** dengan no. faktur; belum ada purchase order | PO di luar checklist Fase 5. |

---

## 7. Verifikasi

- `composer check` (Pint, Larastan level 5, Pest): semua lulus.
- Test ledger (`StockLedgerTest`): penerimaan + konversi satuan + harga pokok per satuan dasar; FEFO melewati batch kedaluwarsa, kekurangan ke "Tanpa batch" + alert; BOM saat tanda tangan + HPP di tagihan; penjualan retail + pembalikan saat void; pemusnahan 4 mata; opname sebagai saldo awal + koreksi; transfer; pembacaan ED (termasuk menolak tanggal yang tidak ada, mis. `13/2027`, `30/02/2027`).
- Test halaman (`StockPagesTest`): penerimaan dari halaman Stok, opname hitung → ajukan → setujui (stok = hasil hitung), pemusnahan → setujui → PDF berita acara, akses PDF per cabang, batch & kartu stok, BOM & perkiraan HPP, order lab dari kunjungan sampai dipasang, batal order, widget, pembatalan penerimaan (dan penolakan bila sudah terpakai).
- `AccessControlTest`: 16 kasus akses baru (Stok, Mutasi, Opname, Supplier, Kategori, Order Lab per peran).
- Dicek di browser dengan data demo: dasbor, Stok (Kemang), mutasi & berita acara, opname, order lab & detail, kartu stok, BOM, form penerimaan.

Data demo (`php artisan db:seed --class=DemoSeeder`): opname awal Kemang sudah disetujui, batch hampir kedaluwarsa dan sudah kedaluwarsa, 2 barang di bawah minimum, satu penerimaan, satu transfer ke Depok, satu pemusnahan menunggu persetujuan admin, tiga order lab berjalan (satu terlambat).

---

## 8. Berikutnya (Fase 6)

- Jasa dokter memakai `invoice_items.material_cost` dan `lab_cost` yang sudah tersimpan (keputusan owner: basis setelah diskon; dikurangi biaya lab tergantung jenis tindakan).
- Laporan stok & mutasi, nilai persediaan, nilai bahan kedaluwarsa per bulan (PDF/Excel).
- Notifikasi terjadwal stok minimum/ED dan order lab jatuh tempo, bila dibutuhkan.
