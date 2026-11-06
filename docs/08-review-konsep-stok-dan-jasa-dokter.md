# Review: Konsep Stok BHP & Pendapatan Dokter (bahan dari klinik)

> Bahan yang direview: workbook *Data dummy pasien, harga perawatan, BHP, BHP expired* (5 sheet) dan dua ERD (*Sistem Stok BHP*, *Sistem Pendapatan Dokter*).
> Tujuan: memakai konsep klinik ini di **Fase 5 (Stok)** dan **Fase 6 (Komisi)**, disesuaikan dengan yang sudah dibangun di Fase 1–3.
> Dokumen ini sengaja **tidak menyalin data pasien** dari workbook.

---

## 1. Ringkasan

| Bagian | Bisa langsung dipakai | Perlu disesuaikan |
|---|---|---|
| ERD Stok BHP | Batch per tanggal kedaluwarsa, transaksi MASUK/KELUAR beralasan, saldo dihitung (bukan diketik), tanpa barcode | Cabang, konversi satuan, harga beli per batch, sumber pemakaian (kunjungan/tindakan), supplier, opname, pemusnahan |
| ERD Pendapatan Dokter | Persentase per jenis jasa (umum 40%, konsultasi 100%), snapshot harga & % per tindakan | Dokter per baris tindakan (bukan per kunjungan), basis diskon/bahan/lab, kapan jasa diakui, status harian dokter |
| Sheet harga prosedur (149 baris) | Jadi master Tindakan & Tarif | Duplikat nama, tarif "Inova Care", promo, barang yang tercampur dengan tindakan, belum ada kode ICD-9-CM |
| Sheet BHP & BHP expired | Daftar barang awal & bahan untuk pemusnahan | Saldo 2023 tidak bisa jadi saldo awal; perlu stok opname baru |
| Sheet data pasien | Struktur kolom untuk import pasien | NIK rusak karena format angka Excel; tanggal tidak seragam |

---

## 2. ERD Stok BHP

### Yang sudah tepat
- **Tanggal kedaluwarsa per batch**, satu barang banyak batch. Sama dengan rencana `item_batches`.
- **Saldo dihitung dari transaksi**, tidak diketik manual. Sama dengan rencana `stock_mutations` (ledger).
- **Alasan transaksi** (pemakaian, kedaluwarsa, rusak, koreksi) disimpan, dan koreksi lewat transaksi baru. Sesuai aturan "tidak ada hapus permanen".
- Barang tanpa ED boleh, tanpa scanner. Sudah ada di master: `items.track_expiry`.

### Yang perlu ditambah (sudah direncanakan di Planning §5.6 / §6.3)

| # | Kebutuhan | Kenapa | Di skema aplikasi |
|---|---|---|---|
| 1 | **Cabang** pada batch & transaksi | Stok Kemang dan Depok terpisah; ada transfer antar cabang | `item_batches.branch_id`, `stock_mutations.branch_id` |
| 2 | **Konversi satuan** | Di sheet expired: "1 kotak = 20 ampul × 2 ml", "kotak 50 ampul". Beli per kotak, pakai per ampul. | Sudah ada sejak Fase 1: `item_units.factor_to_base`; semua jumlah disimpan dalam satuan dasar |
| 3 | **Harga beli per batch** | HPP per tindakan, margin, dan komisi berbasis "neto − bahan" | `item_batches.cost_price` |
| 4 | **Sumber pemakaian** | Pemakaian otomatis dari BOM tindakan & resep; HPP per kunjungan | `stock_mutations.source_type/source_id` (kunjungan, penerimaan, opname, transfer) |
| 5 | **Jenis transaksi terstruktur**, bukan teks bebas | Laporan "nilai bahan kedaluwarsa per bulan" butuh kategori pasti | Enum: penerimaan, pemakaian, retur supplier, transfer keluar/masuk, kedaluwarsa, rusak, koreksi opname |
| 6 | **Supplier & penerimaan barang** | Riwayat beli, harga, no. faktur | `goods_receipts`, `goods_receipt_items`, `suppliers` |
| 7 | **Stok opname dengan persetujuan** | Selisih fisik vs sistem jadi transaksi koreksi | `stock_opnames`, `stock_opname_items` |
| 8 | **FEFO** | Saat keluar, batch dengan ED terdekat dipakai dulu (otomatis) | Logika di service pemakaian |
| 9 | **Pemusnahan bahan kedaluwarsa + BAP** | Sheet expired berisi 59 bahan yang harus dimusnahkan | Transaksi "kedaluwarsa" berkelompok, disetujui, bisa dicetak sebagai BAP |
| 10 | **Petugas & penyetuju** sebagai user, bukan teks | Audit | `created_by`, `approved_by` |

### Catatan desain
- `BATCH_BHP.jumlah_sisa` dan ledger sama-sama menyimpan saldo. Boleh, asal **ledger yang menjadi acuan**, dan `jumlah_sisa` hanya cache yang diperbarui di transaksi database yang sama (row lock). Ini sesuai catatan "jangan menggandakan saldo manual" di ERD.
- `KATEGORI_BHP` dipakai. Aplikasi saat ini hanya punya `items.type` (obat / bahan / alat / retail), yang tetap perlu untuk KFA & SATUSEHAT. Usul: tambah tabel kategori (mis. Restorasi, Endodonti, Anestesi, Ortho, Habis pakai umum) untuk laporan.
- `TRANSAKSI_STOK` (header) + `DETAIL_TRANSAKSI_STOK` (baris per batch) adalah pola yang baik untuk penerimaan, pemusnahan, dan transfer. Untuk pemakaian otomatis per tindakan, satu kunjungan = satu header.

---

## 3. ERD Pendapatan Dokter

### Yang sudah tepat
- **Rumus jasa** `jumlah × harga_saat_transaksi × persentase_saat_transaksi`, dengan harga & % di-snapshot per tindakan. Sama dengan prinsip aplikasi: `invoice_items` sudah menyimpan harga, diskon, dan neto; ada kolom `commission_rule_snapshot` untuk Fase 6.
- **Persentase per jenis jasa** (umum 40%, konsultasi 100%) mudah dipahami dan dikelola.
- **Tarif dengan tanggal mulai berlaku** berguna untuk menjadwalkan kenaikan harga.
- **Pembayaran bisa dicicil.** Sudah didukung Fase 3: satu tagihan banyak pembayaran, paket & cicilan.

### Yang perlu diputuskan / disesuaikan

| # | Hal | Masalah bila mengikuti ERD apa adanya | Usul |
|---|---|---|---|
| 1 | **Dokter per baris tindakan** | ERD mengambil dokter dari `KUNJUNGAN.id_dokter`. Bila dua dokter menangani satu kunjungan (mis. umum + Sp.Ort), jasa salah orang. | Pakai operator per tindakan. Sudah ada: `visit_procedures.operator_id` → `invoice_items.practitioner_id`. |
| 2 | **Basis: kotor atau setelah diskon?** | Di data ada "disc. 50.000". Bila jasa dihitung dari harga kotor, klinik yang menanggung diskon. | Pilih per jenis jasa: kotor / neto (setelah diskon). Aplikasi sudah menyimpan `net_amount` per baris. |
| 3 | **Bahan & lab mahal** | Bracket Damon Rp20–25 jt, crown, gigi tiruan: 40% dari harga kotor bisa melebihi margin. | Basis "neto − biaya lab/bahan" untuk jenis jasa prostodonti & ortho (opsi sudah ada di Planning §5.7). |
| 4 | **Kapan jasa diakui** | ERD tidak menentukan. Untuk cicilan ortho, jasa saat tindakan vs saat lunas berbeda jauh. | Default: saat tagihan lunas. Ortho: proporsional per pembayaran atau per kontrol. **Perlu keputusan owner.** |
| 5 | **Urutan aturan** | Hanya per jenis jasa. | Prioritas: dokter + tindakan → dokter + jenis jasa → tindakan → jenis jasa → default. Kasus khusus (mis. dokter tamu) tetap bisa diatur tanpa mengubah semua tarif. |
| 6 | **Jenis jasa di master tindakan** | `treatments.category` saat ini adalah kategori klinis (untuk warna jadwal), belum tentu sama dengan kelompok bagi hasil. | Tambah kolom/tabel **jenis jasa** terpisah di master tindakan. |
| 7 | **`STATUS_DOKTER_HARIAN` (ON CALL / TAP / libur)** | Belum jelas pengaruhnya ke uang (uang hadir? jaminan minimum?). Jadwal & cuti sudah ada di Fase 2, tetapi status kehadiran harian belum. | Konfirmasi arti "TAP" dan aturannya. Bila memengaruhi pendapatan, tambahkan tabel kehadiran dokter di Fase 6. |
| 8 | **Void / refund** | Tidak ada pembalikan jasa. | Entri jasa *reversal* saat tagihan di-void/refund (Planning §5.7). |
| 9 | **Asisten / perawat** | Tidak ada di ERD. | Bila tidak ada bagi hasil untuk asisten, cukup operator. Konfirmasi. |

---

## 4. Sheet "Harga Prosedur" → master Tindakan & Tarif

149 baris. Bisa diimpor lewat importer Tindakan (Fase 1) setelah dirapikan:

1. **Duplikat dengan harga berbeda.** Contoh: "Penambalan gigi Komposit Besar" 700.000 vs "penambalan komposit besar" 600.000; "Dental Spa" 500.000 vs "Dental Spa (scaling+stain+polishing)" 700.000. Pilih satu nama per tindakan, atau beri nama yang membedakan isinya.
2. **Varian "(inova care)" — 12 baris.** Ini tampak seperti **tarif khusus penjamin/mitra**, bukan tindakan baru. Usul: buat "Inova Care" sebagai penjamin, lalu buat **tarif per penjamin** (fitur baru, kecil). Saat ini aplikasi baru mendukung tarif per cabang. Bila tetap dibuat sebagai tindakan terpisah, laporan diagnosa/tindakan akan terpecah.
3. **Promo** ("bracket Metal Standart Promo", "Scaling promo" di data kunjungan). Lebih rapi sebagai **diskon** di kasir (Fase 3) atau harga paket, bukan tindakan baru.
4. **Barang, bukan tindakan:** "karet elastic 100 pcs", "crimpable hook", "lingual button satuan", "ligature per unit", dan sikat gigi ortho. Lebih tepat masuk **Obat & Bahan** (retail/bahan) dengan harga jual, supaya stoknya ikut terpotong.
5. **Ortho:** "Bracket …" + "Kontrol ortho …" cocok dipetakan ke **Paket & cicilan** (Fase 3): harga paket + kuota kontrol.
6. **"Administrasi" 50.000 vs "Biaya Administrasi" 0.** Pilih satu.
7. **Kolom yang belum ada tetapi dibutuhkan:** kode tindakan, kategori, **kode ICD-9-CM** (wajib untuk SATUSEHAT, Fase 4), durasi (untuk jadwal), dan jenis jasa (untuk Fase 6).
8. **Nama di data kunjungan tidak selalu sama dengan daftar harga.** 11 nama tidak cocok, mis. "Base it liner/CaOH" ditulis dengan 5 cara, "anastesi tropikal" vs "topikal", "Penambalan GIC Giomer". Di aplikasi ini hilang dengan sendirinya karena tindakan dipilih dari master.

---

## 5. Sheet BHP & BHP expired

- **"BAP Daftar Barang" / "BAP Catatan Stok"** (Mei 2023): kartu stok lama per barang. Satuan bercampur di dalam angka ("50 pcs", "2 bungkus", "2 PASANG"), dan beberapa nama tab berbeda dengan nama barang (mis. tab "Sarung scaler" berisi "Alice dental occlusal bite"). **Tidak bisa dijadikan saldo awal.** Saldo awal sebaiknya dari **stok opname pertama** di aplikasi (DoD Fase 5).
- **"Bahan Expired"** (59 baris, status per 27-10-2026). Transkripsinya rapi: ED yang hanya bulan dinormalisasi ke akhir bulan, dan isi kemasan dicatat. Hampir semua sudah kedaluwarsa, termasuk anestesi (Pehacain ED Juni 2026, Scandonest 2024). Ini alasan kuat untuk:
  - **alert ED ≤ 30 hari** dan FEFO di Fase 5 (sudah direncanakan)
  - **alur pemusnahan + cetak BAP**, untuk dimasukkan sebagai satu transaksi "kedaluwarsa" saat stok awal diinput
  - **aturan "bulan saja → akhir bulan"** dipakai juga di form penerimaan barang

---

## 6. Sheet data pasien (untuk import)

- **NIK rusak karena format angka di Excel.** 27 dari 33 NIK berakhiran "000"/"0000", dan panjangnya 14–16 digit. Excel hanya menyimpan 15 digit angka, sehingga digit terakhir hilang. NIK seperti ini **tidak bisa dipakai** untuk mencari IHS di SATUSEHAT. Usul: kumpulkan ulang NIK dari KTP/KK saat pasien datang, dan di Excel selalu format kolom NIK sebagai **Teks**.
- **Tanggal** ditulis dalam beberapa format ("Selasa, 22 April 2025", "Rabu,07/05/2025", "Kamis,03-Juli-2025", "Minggu/26/10/2025"). Ada juga baris judul bulan di tengah data, dan kunjungan dengan beberapa tindakan dipecah ke baris lanjutan tanpa nama pasien. Perlu dirapikan sebelum import.
- **Umur, bukan tanggal lahir.** Aplikasi butuh tanggal lahir. Dari umur hanya bisa diperkirakan.
- **No. RM** sudah konsisten (MR000xxx) dan bisa dipakai sebagai nomor RM lama (prefix `MR` + pad 6 di `.env`). Beberapa sel diawali spasi.
- **Saran import:** cukup **master pasien** (RM, nama, NIK yang sudah diverifikasi, jenis kelamin, tanggal lahir, alamat, HP). Riwayat kunjungan lama **jangan** dimasukkan sebagai rekam medis bertanda tangan, karena akan ikut terkirim ke SATUSEHAT. Simpan sebagai catatan riwayat atau arsip.
- **Privasi:** kalau data ini data pasien asli (nama, NIK, alamat), workbook ini adalah data pribadi kesehatan (UU PDP). Jangan simpan di repositori atau grup chat. Repositori ini tidak menyimpan salinannya.

---

## 7. Keputusan yang dibutuhkan dari klinik (sebelum Fase 5–6)

1. Jasa dokter dihitung dari harga **kotor** atau **setelah diskon**? Apakah biaya **lab/bahan** dipotong dulu untuk ortho, crown, gigi tiruan?
2. Jasa diakui **saat tindakan** atau **saat lunas**? Untuk ortho cicilan: per pembayaran atau per kontrol?
3. Arti **ON CALL / TAP** dan pengaruhnya ke pendapatan dokter.
4. **Inova Care**: penjamin/mitra dengan tarif khusus, atau program diskon?
5. Daftar **jenis jasa** dan persentasenya selain "umum 40%" dan "konsultasi 100%".
6. Siapa yang **menyetujui** pemusnahan bahan kedaluwarsa dan koreksi opname?
