# Fase 3: Kasir & Billing — Catatan Implementasi

> Status: **selesai dari sisi kode** · 6 November 2026
> Acuan: [Planning v2 §5.4 & §7, Fase 3](./02-dental-pms-planning-v2.md#fase-3-kasir--billing-minggu-78) · tampilan mengikuti mockup v2 (Kasir, Tutup kasir, Paket & cicilan, Cetak struk)

---

## 1. Yang Sudah Dibangun

| Checklist Fase 3 | Status | Di aplikasi |
|---|---|---|
| Invoice otomatis dari kunjungan + item tambahan + diskon + porsi penjamin | ✅ | **Keuangan → Kasir**: daftar pasien hari ini yang sudah diperiksa. Tombol **Kasir** membuat draf tagihan dari tindakan (tarif snapshot) dan resep (harga jual obat × konversi satuan). Kasir bisa menambah item (tindakan, obat/bahan, atau biaya lain), memberi diskon per baris (Rp) dan diskon tagihan (Rp atau %), serta mengisi porsi penjamin. Tombol yang sama ada di menu **Kunjungan**. |
| Pembayaran multi-metode/split/DP, deposit, void/refund dengan approval | ✅ | Jendela **Terima pembayaran**: beberapa metode sekaligus (Tunai, QRIS, Transfer, Debit, Kartu kredit, Deposit), tagihan lama bisa **ditagih sekalian** (dialokasikan ke yang terlama dulu), kembalian tunai dihitung. **Deposit**: tab di halaman pasien (top up, riwayat saldo, ajukan refund saldo). **Void & refund** diajukan kasir dan disetujui owner di **Persetujuan void & refund**. |
| Paket & cicilan ortho | ✅ | **Master Klinis → Paket perawatan** (harga + kuota tindakan). **Keuangan → Paket & cicilan**: jual paket ke pasien (DP + jumlah cicilan + tanggal jatuh tempo), jadwal cicilan otomatis, tombol **Terima** per cicilan, daftar cicilan lewat jatuh tempo. Tindakan yang tercakup paket bertarif Rp0 di tagihan kunjungan dan memakai kuota saat tagihan diterbitkan. |
| Tutup kasir harian | ✅ | **Keuangan → Tutup kasir**: sesi kasir per kasir per cabang. Tutup sesi = cocokkan tiap metode bayar dengan laporan bank/EDC/merchant, hitung tunai per pecahan, alasan selisih (wajib bila ada), kas awal besok, setoran ke brankas. Angka dikunci setelah ditutup. |
| Nota PDF + struk thermal 80/58 mm | ✅ kode · ⏳ printer klien | **Cetak struk**: halaman HTML siap cetak (`window.print()`, `@page` 80 mm atau 58 mm), satu struk per transaksi (bisa beberapa tagihan) atau cetak ulang per tagihan. **Nota PDF** A5. |
| Test otomatis rumus tagihan, alokasi diskon, status invoice | ✅ | Lihat §3. |

**DoD Fase 3** (soft launch 1 cabang): alur pendaftaran → periksa → kasir → struk → tutup kasir sudah berjalan dan diuji otomatis. Uji printer thermal klinik dan latihan kasir menunggu staging.

### Alur kasir (ringkas)

1. Dokter selesai memeriksa → pasien muncul di **Kasir**.
2. Klik **Kasir** → draf tagihan terisi otomatis. Ubah bila perlu (diskon, item tambahan, penjamin).
3. **Terima pembayaran** → bila sesi kasir belum dibuka, isi kas awal (sesi dibuka otomatis). Draf diterbitkan (diberi nomor) saat dibayar.
4. Notifikasi berisi tombol **Cetak struk**.
5. Akhir shift: **Tutup kasir**.

### Rumus (Planning §5.4)

```
subtotal_item  = qty × harga − diskon_item
subtotal       = Σ subtotal_item
total          = subtotal − diskon_tagihan + pajak + pembulatan
porsi_pasien   = total − porsi_penjamin
sisa           = porsi_pasien − Σ pembayaran aktif (termasuk deposit) + Σ refund yang sudah diserahkan
```

Diskon tagihan dialokasikan pro-rata ke setiap baris (metode sisa terbesar, jumlahnya selalu pas) dan disimpan di `invoice_items.allocated_invoice_discount` serta `net_amount`, untuk komisi & margin di Fase 6. Kode: `App\Services\Billing\InvoiceCalculator`.

---

## 2. Hak Akses (tambahan Fase 3)

| Modul | Owner | Admin | Dokter | Perawat | Resepsionis | Kasir |
|---|---|---|---|---|---|---|
| Tagihan & pembayaran | R | R | — | — | R | CRU |
| Sesi kasir / tutup kasir | R (semua) | R (semua) | — | — | — | CRU (miliknya) |
| Paket pasien & cicilan | R | R | R | — | R | CRU |
| Master paket | CRUD | CRUD | R | — | R | R |
| Deposit pasien | R | R | — | — | R | Top up, ajukan refund |
| Void / refund | **Setujui** | — | — | — | — | Ajukan |

Izin tanpa resource: `Approve:Billing` (owner), `View:Deposit`, `TopUp:Deposit`. Yang menyetujui tidak boleh orang yang sama dengan yang mengajukan.

---

## 3. Verifikasi

- **Test otomatis: 123 test, 471 assertion** (Fase 2: 86). Yang baru:
  - `tests/Unit/InvoiceCalculatorTest.php`: diskon item Rp & %, alokasi diskon tagihan (jumlah selalu pas), diskon %, pajak per baris, pembulatan, batas porsi penjamin.
  - `tests/Feature/BillingFlowTest.php`: tagihan dari tindakan & resep (konversi strip → tablet), penomoran per cabang, baris terkunci setelah terbit, alokasi ke tagihan terlama + kembalian, tolak bayar lebih, deposit + void dengan 4 mata, void tagihan mengembalikan kuota paket & status kunjungan, refund 3 langkah, tutup kasir (wajib cocokkan & alasan selisih), jadwal cicilan, dokter tetap bisa tanda tangan setelah ditagih.
  - `tests/Feature/BillingPagesTest.php`: alur lewat layar Filament (Kasir → draf → diskon → bayar dengan sesi baru), tutup kasir, persetujuan owner, jual paket & terima DP, top up deposit, akses struk/nota per peran & cabang.
  - Matriks akses halaman baru di `AccessControlTest`.
- **Analisis statis:** Larastan level 5 bersih. Pint bersih.
- **Uji browser (Chromium, 1280 & 1440 px):** kasir → draf → terima pembayaran (tagih sekalian saldo lama, kembalian) → struk 80/58 mm → tutup kasir; paket & cicilan; deposit; persetujuan owner; daftar tagihan. Tanpa error JavaScript/HTTP 500.

---

## 4. Keputusan & Penyimpangan

| # | Planning | Implementasi | Alasan |
|---|---|---|---|
| 1 | `invoices.deposit_used` terpisah | Deposit adalah **metode bayar**. Pemakaian tercatat di ledger deposit dan sebagai baris pembayaran. | Satu cara menghitung "sudah dibayar"; void pembayaran deposit otomatis mengembalikan saldo. |
| 2 | Nomor invoice saat dibuat | Nomor diberikan **saat diterbitkan**: `{KODE CABANG}-{YYMM}-{NNNN}`, mis. `KMG-2611-0021`. Draf tanpa nomor. | Draf yang batal tidak membuat nomor bolong. |
| 3 | `payments.invoice_id` saja | Tabel baru `payment_transactions`: satu kali bayar (satu struk) bisa melunasi beberapa tagihan. | Mockup kasir: "tagih sekalian" saldo lama dalam satu pembayaran. |
| 4 | Void dengan approval | Tiga jalur: **void pembayaran** (koreksi salah input, uang tidak berpindah), **void tagihan** (hanya bila tidak ada pembayaran aktif), **refund** (ajukan → disetujui owner → diserahkan kasir dari sesi yang terbuka). | Jelas mana koreksi dan mana uang keluar; rekap sesi kasir tetap benar. |
| 5 | Diskon item nominal/% | Rumus & data mendukung keduanya. Layar: diskon **per baris dalam Rp**, diskon **tagihan Rp atau %**. | Kolom tabel kasir tetap lega di tablet. Diskon % per baris bisa ditambahkan bila dibutuhkan. |
| 6 | Porsi penjamin | Diisi kasir per tagihan. **Piutang/klaim ke penjamin belum dicatat**. | Klaim asuransi/korporat di luar MVP; dibahas bersama laporan (Fase 6). |
| 7 | Paket: kuota & pemakaian | Tindakan yang tercakup paket bertarif Rp0 di tagihan kunjungan; kuota terpakai saat tagihan **diterbitkan** dan kembali bila tagihan di-void. Pendapatan paket masuk lewat tagihan cicilan. | Kuota tidak berkurang untuk draf yang batal. Aturan komisi paket diputuskan di Fase 6. |
| 8 | Jadwal cicilan | Tagihan cicilan dibuat saat kasir menekan **Terima** (bukan otomatis tiap bulan). Cicilan dibagi rata dibulatkan ke bawah per Rp1.000; selisih masuk cicilan terakhir. | Tidak ada tagihan menumpuk untuk pasien yang belum datang. |
| 9 | Pembulatan sebagai setting | `CLINIC_BILLING_ROUNDING` di `.env` (0, 100, 1000), ke kelipatan terdekat. | Belum ada halaman pengaturan; satu nilai untuk semua cabang. |
| 10 | Pajak per item | Kolom & rumus ada (`tax_rate`, default 0), **belum tampil di layar**. | Klinik belum PKP. Aktifkan bila perlu. |
| 11 | — | Kunjungan bisa ditagih **sebelum** dokter menandatangani rekam medis; tanda tangan setelahnya tetap diizinkan dan status tetap "Sudah ditagih". | Dokter sering melengkapi rekam medis setelah pasien pulang. Daftar "Sebelum pasien pulang" menunjukkan statusnya. |
| 12 | — | Menerima uang wajib di **sesi kasir** yang terbuka; sesi dibuka otomatis dari jendela pembayaran bila belum ada. | Semua uang masuk/keluar bisa dicocokkan saat tutup kasir. |
| 13 | — | Baris draf tagihan boleh dihapus (tercatat di audit log). Setelah terbit, baris & angka terkunci di level model. | Draf adalah data kerja sebelum ada nomor dan uang. |

---

## 5. Data Demo

`php artisan db:seed --class=DemoSeeder` sekarang juga mengisi:
- Master paket **Behel metal RA + RB** (Rp8.000.000, 12× kontrol behel).
- Paket untuk pasien kontrol behel jam 08.30: DP + 6 cicilan, sudah dibayar sampai bulan lalu, **cicilan ke-5 lewat jatuh tempo**.
- Tagihan bulan lalu pasien jam 09.00 yang baru dibayar sebagian (**saldo terutang Rp450.000**, muncul di kasir sebagai "tagih sekalian").
- Sesi kasir lama yang sudah ditutup (untuk riwayat).

Seeder tetap aman dijalankan ulang.

---

## 6. Belum Terverifikasi (butuh lingkungan nyata)

- **Printer thermal klinik** (80/58 mm, USB/Bluetooth) dan aplikasi bridge di tablet Android (mis. RawBT). Fallback: nota PDF.
- Tampilan nota PDF A5 di printer klinik.
- Latihan tutup kasir dengan laporan EDC/QRIS asli (nama metode & sumber pencocokan bisa disesuaikan).
- Kebijakan diskon, pembulatan, dan alur refund **wajib dikonfirmasi owner klinik** sebelum soft launch.
