# Fase 6: Jasa Dokter, Gaji, Laporan & Dashboard — Catatan Implementasi

> Status: **selesai dari sisi kode** · 6 November 2026 · **DoD menunggu rekap bulan pertama dicocokkan dengan hitungan manual klinik** (§9)
> Acuan: [Planning v2 §5.7–5.8, Fase 6](./02-dental-pms-planning-v2.md#fase-6-komisi-laporan--dashboard-minggu-1314) · [Review konsep jasa dokter & jawaban owner](./08-review-konsep-stok-dan-jasa-dokter.md) · mockup v2 "Komisi", "Dashboard owner", "Huddle"

---

## 1. Yang Sudah Dibangun

| Checklist Fase 6 | Status | Di aplikasi |
|---|---|---|
| Aturan komisi, perhitungan, reversal, rekap & slip PDF | ✅ | **Jasa & Gaji → Aturan jasa** (prioritas, basis, persen/nominal, kapan diakui), **Rincian jasa** (per tindakan), **Rekap jasa dokter** (per bulan: buat/perbarui, tambahan/potongan, final, dibayar, slip PDF, Excel), **Kehadiran dokter** (uang hadir & on call). Void/refund membuat entri pembalik. |
| Gaji staf (komponen manual) + slip PDF + rekap Excel | ✅ | **Komponen gaji**, komponen tetap per karyawan di **Pengguna → Komponen gaji**, **Slip gaji staf** (buat slip bulan ini, ubah baris selama draf, final, dibayar, slip PDF A5, rekap Excel). PPh 21 diisi manual. |
| Job snapshot `daily_metrics` + target per dokter/cabang | ✅ | `metrics:snapshot` tiap 00:30 (isi mundur: `--from=YYYY-MM-DD`, hitung ulang: `--force`). **Laporan → Target produksi** per cabang atau per dokter; target harian dari hari kerja di jadwal praktik. |
| Dashboard per role + Morning Huddle + tooltip definisi KPI | ✅ | Widget per peran (§5) dan **Harian → Briefing pagi**. Definisi KPI ditulis sekali (`App\Enums\Kpi`) dan muncul sebagai tooltip ⓘ, catatan kaki, laporan, dan email. |
| Laporan omzet, arus kas, laba kotor, piutang, komisi, stok, kunjungan, top diagnosa (PDF/Excel) | ✅ | **Laporan → Laporan**: 10 laporan (§7), tampilan di layar sama dengan file Excel/PDF yang diunduh. |
| Ringkasan harian ke email owner | ✅ | `reports:daily-summary` tiap 21:00 ke semua owner aktif. Perlu SMTP di `.env` (§8). |
| Test otomatis aturan komisi (semua basis) & definisi KPI | ✅ | `CommissionTest`, `ReportingTest`, `PayrollPagesTest` (§10). |

---

## 2. Jasa Dokter

### Keputusan owner yang diterapkan (review 6 Nov 2026)

| # | Pertanyaan | Jawaban owner | Di aplikasi |
|---|---|---|---|
| 1 | Basis jasa | Setelah diskon; biaya lab tergantung jenis tindakan | Basis per aturan. Aturan awal: "setelah diskon" untuk konsultasi & tindakan umum; "setelah diskon − bahan − lab" untuk tindakan dengan lab & ortodonti |
| 2 | Kapan diakui | Umum: saat tindakan dikerjakan; ortho: per kunjungan kontrol | Pengakuan **saat tindakan dikerjakan** (tagihan terbit). Kontrol ortho yang tercakup paket dihitung dari **nilai paket per kontrol** |
| 3 | ON CALL / TAP | Ada uang hadir / on call | Tarif **uang hadir** dan **on call** per dokter (di data nakes, hanya owner) + menu **Kehadiran dokter** |
| 4 | Inova Care | Program diskon/promo (belum pasti) | Dicatat sebagai diskon biasa di tagihan. Karena basis "setelah diskon", diskon promo ditanggung bersama klinik & dokter |
| 5 | Jenis jasa & persentase | "Menurut mu apa" | Usulan dipasang sebagai aturan awal (lihat di bawah). **Persentase tetap keputusan owner** dan bisa diubah kapan saja |

**Aturan awal** (dibuat sekali oleh seeder, catatan "Usulan awal" di tiap aturan):

| Aturan | Berlaku untuk | Perhitungan |
|---|---|---|
| Konsultasi | Jenis jasa Konsultasi | 100% setelah diskon |
| Tindakan umum | Jenis jasa Tindakan umum | 40% setelah diskon |
| Tindakan dengan lab | Jenis jasa Tindakan dengan lab (prostodonti) | 40% dari (setelah diskon − bahan − biaya lab) |
| Ortodonti | Jenis jasa Ortodonti | 40% dari (setelah diskon − bahan − biaya lab) |
| Default | Semua yang tidak cocok di atas | 40% setelah diskon |

Data demo juga punya contoh **asisten Rp 10.000 per tindakan** (dari mockup "Insentif staf non-dokter"); belum ada di data produksi sampai owner memutuskan.

### Cara kerja

- **Jenis jasa** ada di master tindakan, terpisah dari kategori klinis. Awalnya mengikuti kategori (konsultasi → Konsultasi, prostodonti → Tindakan dengan lab, ortodonti → Ortodonti, lainnya → Tindakan umum).
- **Prioritas aturan** (yang paling spesifik dipakai): dokter + tindakan → dokter + jenis jasa → dokter → tindakan → jenis jasa → default. Operator dan asisten punya aturan masing-masing.
- **Snapshot**: saat tagihan terbit, aturan yang berlaku disimpan di baris tindakan (`invoice_items.commission_rule_snapshot`), lalu dibuat satu entri jasa per tindakan per peran dengan bruto, diskon, bahan, lab, basis, dan jasa. Mengubah aturan atau tarif tidak mengubah bulan yang sudah lewat.
- **Rumus**: bruto = jumlah × harga; setelah diskon = bruto − diskon baris − bagian diskon tagihan (sebelum pajak); basis sesuai aturan (tidak pernah negatif); jasa = basis × persen, atau nominal × jumlah.
- **Bahan** dari pemakaian stok tindakan itu (Fase 5), **lab** dari order lab tindakan itu yang tidak batal.
- **Ortho per kontrol**: tindakan yang tercakup paket bertarif 0 di tagihan; nilainya = harga paket dibagi ke kuota paket, ditimbang dengan tarif tindakan. Paket Rp 8 jt berisi 12× kontrol → Rp 666.667 per kontrol. Cicilan paket **tidak** menghasilkan jasa (supaya tidak dihitung dua kali).
- **Void tagihan**: entri yang belum masuk rekap final dibatalkan; yang sudah final/dibayar dibalik (entri negatif) dan masuk rekap berikutnya.
- **Refund sebagian**: entri pembalik sebanding porsi refund terhadap total tagihan.
- **Susulan**: tagihan yang terbit saat belum ada aturan sama sekali disusulkan (dengan aturan saat itu) ketika rekap dibuat.
- **Rekap bulanan**: semua jasa yang sudah diakui s.d. akhir bulan dan belum direkap (termasuk susulan & pembalik), uang hadir/on call, tambahan/potongan manual (mis. PPh 21, kasbon). Draf bisa dibuat ulang; final mengunci; dibayar menandai entri "Dibayar".
- **Dokter** hanya melihat rincian, rekap, dan slip miliknya.

---

## 3. Contoh Hitung (untuk mencocokkan dengan hitungan manual)

| Tindakan | Harga | Diskon | Bahan | Lab | Aturan | Basis | Jasa |
|---|---:|---:|---:|---:|---|---:|---:|
| Konsultasi | 100.000 | 0 | 0 | 0 | 100% setelah diskon | 100.000 | 100.000 |
| Tambal komposit | 400.000 | 40.000 (promo 10%) | 69.000 | 0 | 40% setelah diskon | 360.000 | 144.000 |
| Crown PFM | 2.000.000 | 200.000 | 50.000 | 650.000 | 40% (setelah diskon − bahan − lab) | 1.100.000 | 440.000 |
| Kontrol behel (paket Rp 8 jt, 12×) | 0 (paket) | — | 0 | 0 | 40% (setelah diskon − bahan − lab) | 666.667 | 266.667 |

Rekap = Σ jasa + uang hadir/on call + tambahan − potongan. Contoh: jasa Rp 950.667 + 2 hari hadir × Rp 150.000 − PPh 21 Rp 25.000 = **Rp 1.225.667**.

---

## 4. Gaji Staf (MVP)

- **Komponen gaji**: pendapatan/potongan (gaji pokok, uang makan, tunjangan transport, lembur, BPJS, PPh 21 manual, kasbon) dengan nominal default.
- **Komponen tetap per karyawan**: Pengguna → (karyawan) → tab **Komponen gaji** (owner).
- **Slip bulanan**: "Buat slip bulan ini" menyalin komponen tetap ke slip draf; baris bisa diubah/ditambah selama draf; **Finalkan** mengunci; **Tandai dibayar**. Slip PDF (A5, tanda tangan penerima & penyetuju) dan rekap Excel per periode (satu kolom per komponen).
- Jasa dokter dibayar lewat **Rekap jasa dokter**, bukan slip gaji.

---

## 5. KPI, Snapshot, Target & Dashboard

**Definisi** (sama persis dengan tooltip): lihat `App\Enums\Kpi`. Ringkas:

| KPI | Definisi |
|---|---|
| Produksi | Σ nilai setelah diskon baris tagihan dari kunjungan selesai (termasuk nilai paket terpakai), menurut tanggal kunjungan; tagihan void tidak dihitung |
| Kas masuk | Σ pembayaran (selain dari saldo deposit) + top-up deposit − refund yang diserahkan, menurut tanggal bayar |
| Kas / produksi | Kas masuk ÷ produksi |
| Capaian target | Produksi ÷ (target harian × hari kerja yang sudah lewat); merah < 85%, kuning 85–95%, hijau > 95% |
| Pasien baru | Kunjungan selesai pertama jatuh di periode |
| Utilisasi kursi | Σ menit janji yang datang + kunjungan tanpa janji ÷ Σ menit praktik dari jadwal (dikurangi cuti) |
| Tidak datang | Janji "tidak datang" ÷ janji terjadwal; nilai hilang = jumlah × rata-rata produksi per kunjungan 90 hari |
| Penerimaan rencana | Nilai rencana disetujui ÷ nilai rencana diajukan |
| Rencana belum dijadwalkan | Nilai item rencana disetujui, belum dikerjakan, pasien belum punya janji |
| Piutang | Sisa porsi pasien per umur tagihan 0–30 / 31–60 / 61–90 / > 90 hari |
| Margin | Setelah diskon − bahan − lab − jasa dokter |

**Snapshot**: angka per hari per cabang (dan per dokter) disimpan pukul 00:30, jadi angka kemarin tidak berubah walau ada void/koreksi sesudahnya (laporan selalu dihitung langsung). Hari ini selalu dihitung langsung. Snapshot yang belum ada dibuat saat halaman dibuka (maks. 70 hari × cabang per buka); untuk data lama jalankan sekali `php artisan metrics:snapshot --from=2026-01-01`.

**Target**: per bulan, per cabang atau per dokter di cabang. Target harian = target bulan ÷ hari kerja (hari yang punya jadwal praktik, dikurangi cuti seharian). Tanpa target cabang, dashboard memakai jumlah target dokter di cabang itu.

**Dashboard per peran**:

| Peran | Widget |
|---|---|
| Owner | Per cabang (produksi, target s.d. kemarin, capaian, kas masuk, kas/produksi, pasien baru, tidak datang, rencana belum dijadwalkan; bulan berjalan / bulan lalu) · produksi per bulan 12 bulan · per dokter (produksi, capaian, kunjungan, penerimaan rencana, estimasi jasa) · **top tindakan dengan margin** · rencana belum dijadwalkan · saldo terutang > 30 hari + umur piutang + sisa cicilan · top-10 diagnosa · SATUSEHAT · stok & lab |
| Admin | Sama dengan owner **tanpa margin**, plus meja depan & daftar perlu dihubungi |
| Dokter | Pasien saya hari ini (pasien baru, alergi/penyakit sistemik, rencana tertunda, recall terlambat, saldo, lab belum kembali, ulang tahun) · kinerja saya (produksi kemarin & bulan ini vs target, estimasi jasa, penerimaan rencana) · stok & lab |
| Resepsionis | Meja depan (janji hari ini & besok, menunggu & rata-rata waktu tunggu, slot kosong besok) · perlu dihubungi (recall, rencana belum dijadwalkan, cicilan jatuh tempo) |
| Kasir | Kas hari ini per metode · pasien hari ini belum lunas · tagihan dibayar sebagian · stok |
| Perawat | Top-10 diagnosa · SATUSEHAT · stok & lab |

---

## 6. Briefing Pagi

**Harian → Briefing pagi** (per cabang, bisa dicetak):
- **Hari ini**: jumlah janji & yang dikonfirmasi, perkiraan produksi vs target harian, jadwal per kursi/dokter dengan penanda pasien, slot kosong ≥ 30 menit.
- **Kemarin**: produksi vs target harian, kas masuk, pasien (baru), tidak datang (nilai), pasien yang pulang tanpa janji berikutnya padahal masih punya rencana disetujui atau pekerjaan lab.
- **Besok**: janji & dikonfirmasi, slot kosong, pasien yang bisa dihubungi untuk mengisi slot (dengan nomor HP).

Penanda **alergi/penyakit sistemik** hanya tampil untuk pengguna dengan izin rekam medis (isinya tidak ditampilkan). Setiap buka halaman dicatat di log akses.

---

## 7. Laporan

| Laporan | Isi | Yang boleh |
|---|---|---|
| Omzet | Per cabang / dokter / tindakan / penjamin (dasar produksi) atau per metode bayar (dasar kas) | Owner, admin; dokter (miliknya) |
| Arus kas | Per hari per metode + top-up deposit − refund | Owner, admin, kasir |
| Laba kotor | Per tindakan (setelah diskon − bahan − lab − jasa) + obat & barang (penjualan − HPP) | Owner |
| Piutang | Tagihan belum lunas hari ini dengan umur | Owner, admin |
| Jasa dokter | Per dokter: tindakan, bruto, diskon, bahan, lab, basis, menunggu/diakui/dibayar | Owner, admin; dokter (miliknya) |
| Stok & mutasi | Per barang: saldo awal, masuk, keluar, saldo akhir, nilai | Owner, admin |
| Kedaluwarsa | Batch yang sudah/akan lewat ED (≤ 60 hari) dengan nilai | Owner, admin |
| Kunjungan & pasien baru | Per hari: kunjungan, pasien baru, janji, tidak datang, rata-rata tunggu | Owner, admin |
| Top diagnosa | ICD-10: kasus & pasien (laporan Dinkes) | Owner, admin |
| Tidak datang | Daftar janji tidak datang dengan nomor HP | Owner, admin |

Unduhan (Excel/PDF) memakai filter dan batasan izin yang sama dengan layar, dan dicatat di log akses. Angka di Excel tetap angka (bisa dijumlah); persen dalam %.

---

## 8. Ringkasan Harian ke Email Owner

`reports:daily-summary` pukul 21:00: per cabang produksi (vs target harian), kas masuk, kunjungan, pasien baru, tidak datang, dan stok kritis (di bawah minimum, ED dekat, minus). Tanpa nama/data pasien. Dikirim ke semua pengguna aktif berperan owner.

Agar terkirim, isi SMTP di `/var/www/dental-pms/.env` (`MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`). Dengan `MAIL_MAILER=log` (bawaan) email hanya ditulis ke log. Uji: `php artisan reports:daily-summary`.

---

## 9. DoD: Rekap Bulan Pertama Cocok dengan Hitungan Manual

1. Owner meninjau **Aturan jasa** (persentase & basis per jenis jasa) dan mengisi **uang hadir/on call** tiap dokter, sebelum tagihan pertama bulan itu terbit.
2. Di akhir bulan: **Rekap jasa dokter → Buat / perbarui rekap** → **Unduh Excel** (sheet "Rincian" berisi setiap tindakan dengan bruto, diskon, bahan, lab, basis, aturan, jasa).
3. Bagian keuangan mencocokkan sheet "Rincian" dengan hitungan manual. Bila ada selisih karena aturan, ubah aturan → **void & terbitkan ulang** tagihan yang terdampak (atau tambahkan baris tambahan/potongan dengan keterangan), lalu buat ulang rekap draf.
4. Setelah cocok: **Finalkan** → bayar → **Tandai dibayar** → cetak slip.

---

## 10. Penyimpangan dari Planning & Catatan

| # | Planning | Implementasi | Alasan |
|---|---|---|---|
| 1 | Pengakuan default "saat lunas" | Default **saat tindakan dikerjakan**; "saat lunas" tetap tersedia per aturan | Keputusan owner (q2) |
| 2 | Paket/cicilan: proporsional per pembayaran atau per kontrol | **Per kontrol** dari nilai paket per pemakaian; cicilan tidak menghasilkan jasa | Keputusan owner (q2) |
| 3 | Jasa dihitung saat tindakan | Dihitung saat **tagihan terbit** (tanggal jasa = tanggal kunjungan) | Diskon & alokasi diskon tagihan baru final saat terbit |
| 4 | Jenis jasa bebas | 4 jenis tetap (konsultasi, umum, dengan lab, ortodonti); kasus khusus lewat aturan per tindakan/dokter | Cukup untuk keputusan owner; laporan konsisten |
| 5 | Snapshot diisi job | Job 00:30 + pengisian saat halaman dibuka (terbatas) + `--from` untuk data lama | Dashboard tidak kosong bila job sempat tidak jalan |
| 6 | Produksi dari invoice item | Termasuk tagihan draf kunjungan selesai (yang belum terbit); hanya void yang dikeluarkan | Kunjungan selesai yang belum ditagih tetap terlihat sebagai produksi |
| 7 | "Kirim slip ke dokter" (mockup) | Cetak slip PDF; belum dikirim otomatis | Kanal kirim (email/WA) belum diputuskan; WhatsApp di luar scope |
| 8 | Gaji: PPh 21 | Manual per slip | Sesuai planning (otomatis masuk backlog setelah konsultan pajak) |
| 9 | Target | Hanya metrik produksi | Satu-satunya target di planning |
| 10 | Ringkasan harian "setiap malam" | 21:00 dengan angka hari itu (live) | Angka final hari itu tersimpan di snapshot 00:30 |

**Perlu dikonfirmasi owner**: persentase final per jenis jasa, nominal uang hadir/on call per dokter, apakah asisten/perawat mendapat insentif (contoh demo Rp 10.000 per tindakan), dan perlakuan Inova Care bila ternyata bukan sekadar diskon.

---

## 11. Verifikasi

- `composer check` lulus (Pint, Larastan level 5, Pest). Test jasa, laporan, gaji, dan seeder juga lulus di MySQL 8.
- `CommissionTest`: lima basis (bruto, setelah diskon, − bahan, − lab, − bahan − lab) dengan diskon, bahan dari stok, dan lab dari order lab; prioritas aturan; asisten nominal tetap; kontrol ortho dari nilai paket (cicilan tanpa jasa); "saat lunas"; aturan berubah tidak mengubah entri lama; void sebelum/sesudah rekap dibayar; refund sebagian; rekap + uang hadir + potongan → final → dibayar; susulan; uang hadir dari kunjungan.
- `ReportingTest`: definisi KPI per hari (produksi, kunjungan, pasien baru, kas masuk, janji & tidak datang + nilai hilang, menit praktik dikurangi cuti, waktu tunggu, jasa); snapshot beku + `--force`; target harian dari jadwal; penerimaan rencana, rencana belum dijadwalkan, umur piutang; semua laporan untuk owner, batasan dokter & kasir; unduh Excel/PDF + 403; halaman laporan, briefing pagi, dashboard semua peran; setiap widget dengan data; email ringkasan tanpa nama pasien.
- `PayrollPagesTest`: aturan jasa dari halaman; rekap dari halaman sampai dibayar + slip & Excel; dokter hanya melihat rekap/slip miliknya; kehadiran dari kunjungan & manual; slip gaji dari komponen karyawan sampai final + PDF/Excel.
- `AccessControlTest`: 27 kasus akses baru.
- Dicek di browser dengan data demo (owner, dokter, resepsionis, kasir).

Data demo (`php artisan db:seed --class=DemoSeeder`): ±4 minggu kunjungan selesai di Kemang & Depok (tagihan, pembayaran, ±10% belum lunas, promo 10%, tidak datang), rencana perawatan, target, uang hadir, rekap jasa bulan lalu (final), komponen gaji & slip bulan lalu (final), dan snapshot KPI.
