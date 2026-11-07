# Pembelian BHP — permintaan, pesanan, penerimaan, kas pembelian

> 12 November 2026 · Asal: aplikasi inventori BHP buatan klinik (proyek KP) yang sudah dipakai staf · Status proyek lain tetap di [docs/11](./11-fase-7-implementasi.md), perapian UI di [docs/12](./12-perapian-ui.md)

Klinik sudah membuat aplikasi inventori sendiri sebelum aplikasi ini siap. Setelah kodenya ditinjau, bagian yang belum ada di modul stok kita adalah **alur pembelian**: perawat meminta barang, admin memesan ke supplier, barang datang (sering sebagian), dan uang belanja dicatat di kas tersendiri. Alur itu sekarang ada di menu **Stok**, memakai buku stok yang sama (batch, ED, FEFO, opname, harga pokok).

---

## 1. Alur

```
Perawat: Minta beli ──► Admin/owner: Setujui (bukan pengaju) ──► Admin: Buat pesanan ──► Perawat/admin: Terima barang
   (tab Barang / Permintaan)        atau Tolak                      (supplier, harga)        (boleh sebagian; stok bertambah)
                                                                          │
                                                       Kas pembelian ◄────┘  bayar sekarang atau nanti
```

| Langkah | Siapa | Yang terjadi |
|---|---|---|
| Permintaan (`PMT-KMG-2611-0001`) | perawat, admin | Satu barang + jumlah + satuan + alasan. Status *Menunggu persetujuan*. |
| Setujui / tolak | admin, owner (`Approve:Purchase`) | Empat mata: pengaju tidak bisa memutuskan permintaannya sendiri. Tolak wajib beralasan. |
| Pesanan (`PSN-KMG-2611-0001`) | admin | Satu pesanan bisa memuat beberapa permintaan + barang lain. Harga per satuan pesan, konversi satuan disimpan (snapshot). **Memesan tidak menambah stok.** |
| Terima barang | perawat, admin (`Receive:Purchase`) | Jumlah yang datang (boleh sebagian), no. batch, ED, no. faktur. Stok bertambah lewat `StockLedger::receive` dengan harga pokok dari harga pesanan; mutasi penerimaan menunjuk ke pesanannya. Jumlah dicek ulang di dalam kunci baris agar tidak melebihi sisa. |
| Tutup sisa pesanan | admin | Supplier tidak mengirim sisanya: pesanan ditutup apa adanya (alasan tercatat). |
| Batalkan pesanan | admin | Hanya yang belum datang sama sekali. Pembayaran kembali ke kas (entri *Pengembalian*), permintaannya kembali *Disetujui* dan bisa dipesan ulang. |
| Kas pembelian | owner, admin (`View/Manage:PurchaseCash`) | Per cabang: setoran, pembayaran pesanan, pengeluaran lain (ongkir, beli langsung). Saldo tidak boleh minus. Entri tidak bisa diubah/dihapus; salah catat dikoreksi dengan entri pembalik. |

Penerimaan dari pesanan yang salah input dibatalkan dari halaman mutasinya (**Batalkan mutasi**): stok dibalik lewat mutasi pembatalan **dan** jumlah diterima di pesanan ikut dikurangi, sehingga pesanan kembali terbuka.

## 2. Layar (menu Stok)

| Tab | Isi |
|---|---|
| **Barang** | Tombol **Minta beli** per baris. Kolom *Perlu tindakan* menulis "sudah diminta PMT-…" atau "dalam pesanan PSN-…" agar tidak diminta dua kali. |
| **Permintaan** | Tab Terbuka · Menunggu persetujuan · Siap dipesan · Selesai & ditolak. Badge menu = permintaan menunggu (hanya untuk penyetuju). Centang yang disetujui → **Buat pesanan**. |
| **Pesanan** | Daftar dengan status bayar ("Lunas", "Kurang 85.000") dan kedatangan ("Lewat 3 hari"). Halaman pesanan: baris dipesan/diterima/sisa, riwayat penerimaan (tautan ke mutasi), riwayat pembayaran. Aksi utama **Terima barang**. |
| Mutasi & penerimaan, Opname | Tetap; penerimaan dari pesanan menampilkan "Dari pesanan PSN-…". |
| **Kas pembelian** | Saldo di subjudul, riwayat dengan kolom Masuk/Keluar/Saldo berjalan, **Setor kas** (aksi utama), **Catat pengeluaran**, **Koreksi** per baris. |

Dashboard: widget *Stok & lab* punya kartu **Pembelian** (pesanan belum datang, permintaan menunggu, pesanan lewat perkiraan datang).

## 3. Hak akses

| | owner | admin | perawat | dokter, resepsionis, kasir |
|---|---|---|---|---|
| Permintaan (lihat / ajukan) | lihat | ✓ | ✓ | — |
| Setujui / tolak (`Approve:Purchase`) | ✓ | ✓ | — | — |
| Pesanan (lihat / buat / tutup / batal) | lihat | ✓ | lihat | — |
| Terima barang (`Receive:Purchase`) | — | ✓ | ✓ | — |
| Kas pembelian (`View/Manage:PurchaseCash`) | ✓ | ✓ | — | — |

## 4. Kode

- Skema: `database/migrations/2026_11_12_000100_create_purchasing_tables.php` (`purchase_requests`, `purchase_orders`, `purchase_order_lines`, `purchase_cash_entries`).
- Semua aturan: `App\Services\Purchasing\Purchasing` (requestItem, approve, reject, cancelRequest, createOrder, receive, cancelReceipt, closeRemaining, cancelOrder, pay, deposit, expense, reverseCash, balance). Kesalahan input = `InvalidArgumentException` berbahasa Indonesia.
- Transaksi kas satu cabang dikunci bergiliran lewat baris `branches` (`lockForUpdate`) sehingga saldo tidak bisa minus walau dua orang menyimpan bersamaan.
- `StockLedger::receive(..., $source)` menyimpan dokumen asal; `StockLedger::cancel` menolak penerimaan yang berasal dari pesanan kecuali dipanggil `Purchasing::cancelReceipt`.
- UI: `app/Filament/Clusters/Stock/PurchaseActions.php`, `Resources/PurchaseRequests`, `Resources/PurchaseOrders`, `Pages/PurchaseCash.php`, view `resources/views/filament/stock/purchase-order.blade.php`.
- Nomor urut lewat `NumberSequence::nextPurchaseRequestNumber` / `nextPurchaseOrderNumber` (per cabang per bulan).

**Perbaikan yang ikut:** tab di halaman *Mutasi & penerimaan* (Penerimaan, Pemakaian & penjualan, dst.) sebelumnya error karena closure tab memakai nama parameter `$q`; Filament menyuntikkan parameter berdasarkan nama, jadi harus `$query`.

## 5. Memindahkan data dari aplikasi inventori lama

Hanya data master (barang, kategori, supplier). Stok awal **dihitung ulang lewat opname pertama** (saldo awal sistem), karena angka di aplikasi lama belum tentu cocok dengan fisik.

1. Di phpMyAdmin aplikasi lama, jalankan query berikut lalu **Export → CSV** (centang "Put columns names in the first row"):

   ```sql
   -- Barang + kategori
   SELECT i.code AS kode, i.name AS nama, i.brand AS merek, c.name AS kategori,
          i.unit AS satuan, i.minimum_stock AS stok_min, i.is_active AS aktif
   FROM bhp_items i LEFT JOIN bhp_categories c ON c.id = i.category_id
   WHERE i.deleted_at IS NULL;
   ```

   ```sql
   -- Supplier (nama perusahaan bila ada, nama orangnya jadi kontak)
   SELECT COALESCE(NULLIF(company, ''), name) AS nama,
          CASE WHEN company IS NOT NULL AND company <> '' THEN name END AS kontak,
          phone AS telepon, email, address AS alamat, notes AS catatan
   FROM suppliers;
   ```

2. Di aplikasi ini: *Master barang → Supplier & lab → Import CSV*, lalu *Obat & bahan → Import CSV*. Kolom dikenali otomatis. Barang tanpa kolom jenis dianggap **bahan (BHP)**; kategori & satuan dibuat bila belum ada; merek ditambahkan di belakang nama ("Masker (Sensi)"). Impor ulang memperbarui baris dengan kode (SKU) / nama supplier yang sama.
3. Isi kemasan (*package_content*, mis. 1 box = 50 pcs) diatur manual di *Obat & bahan → Konversi satuan*.
4. *Stok → Mulai opname* per cabang: hitung fisik, isi batch & ED, ajukan, setujui.

| Aplikasi lama | Di sini |
|---|---|
| bhp_items / bhp_categories | Obat & bahan / Kategori (Master barang) |
| bhp_batches, stock_transactions | Batch & mutasi stok (`StockLedger`, FEFO, opname) |
| purchase_requests (PENDING/DIPROSES…) | Permintaan (`PMT-…`) |
| purchases + purchase_details (ORDERED/PARTIAL/RECEIVED) | Pesanan (`PSN-…`): Menunggu datang / Diterima sebagian / Diterima lengkap |
| cash_transactions | Kas pembelian |
| activity_logs | Audit log (sudah ada untuk semua model) |

## 6. Verifikasi

- `tests/Feature/PurchasingTest.php` (10 tes): empat mata, pesanan dari permintaan, terima sebagian/lengkap lewat buku stok, kelebihan sisa ditolak, tutup sisa, batal + refund + pesan ulang, saldo kas per cabang tidak minus, koreksi sekali, entri kas immutable, pembatalan penerimaan, izin per peran, impor barang/kategori/supplier.
- `tests/Feature/PurchasingPagesTest.php` (6 tes): Minta beli dari tab Barang, setujui + bulk buat pesanan + form pesanan terisi dari permintaan, terima sebagian & bayar dari halaman pesanan, batalkan penerimaan dari halaman mutasi, kas pembelian, semua tab mutasi/permintaan/pesanan termuat, widget.
- `AccessControlTest`: 11 kasus halaman pembelian per peran. Data demo: 3 permintaan, 1 pesanan dibayar & diterima sebagian, kas berisi setoran dan ongkir.
