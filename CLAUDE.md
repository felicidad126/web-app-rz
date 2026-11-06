# Dental PMS — panduan untuk agen/kontributor

Aplikasi manajemen klinik gigi (PWA): Laravel 13, Filament v5, Livewire 4, MySQL, Redis.

- **Acuan desain:** `docs/02-dental-pms-planning-v2.md` (scope, skema DB, roadmap).
- **Status terakhir:** `docs/11-fase-7-implementasi.md` (Fase 1: `docs/03-…`, Fase 2: `docs/05-…`, Fase 3: `docs/06-…`, Fase 4: `docs/07-…`, Fase 5: `docs/09-…`, Fase 6: `docs/10-…`). Review konsep stok & jasa dokter dari klinik: `docs/08-review-konsep-stok-dan-jasa-dokter.md`.

## Perintah

- Cek semua sebelum commit: `composer check` (Pint, Larastan level 5, Pest).
- Deploy server: `deploy/provision.sh` (instalasi pertama, idempoten) dan `deploy/update.sh` (update). Jalankan `shellcheck -S warning deploy/*.sh` setelah mengubahnya.
- Test di MySQL: `DB_CONNECTION=mysql DB_DATABASE=dental_pms_test DB_USERNAME=... DB_PASSWORD=... vendor/bin/pest`.
- Setelah menambah resource Filament:
  1. `php artisan shield:generate --all --panel=admin --option=policies_and_permissions -n`
  2. Tambahkan permission ke `RolePermissionSeeder::matrix()`.
  3. Tambahkan kasus di `tests/Feature/AccessControlTest.php`.

## Aturan

- **Label UI Bahasa Indonesia.** Kode, nama tabel, dan kolom dalam bahasa Inggris.
- **Tidak ada hapus permanen** data klinis, transaksi, atau master. Pakai soft delete atau status void. Jangan menambah `forceDelete` ke policy atau permission.
- **NIK** hanya lewat trait `HasEncryptedNik`:
  - cast `encrypted` + kolom `nik_hash`
  - validasi pakai `NikField` / `UniqueNik`
  - jangan pernah dicatat di log atau audit
- **Audit:** model domain memakai `LogsAudit`. Halaman yang menampilkan data klinis mencatat akses (`activity('access')->event('viewed')`).
- **Rekam medis terkunci** setelah ditandatangani: data klinis milik kunjungan memakai trait `LockedWithMedicalRecord`; koreksi lewat addendum. Izin klinis: `View/Update/Sign:MedicalRecord`.
- **File klinis** (lampiran, tanda tangan, PDF) di disk `local` (privat), dibuka lewat route `clinic.*` yang mengecek izin dan mencatat akses. Jangan simpan di disk `public`.
- **Uang** disimpan sebagai `bigint` rupiah. Simpan snapshot harga dan % fee di baris transaksi.
- **Billing:** rumus tagihan hanya di `App\Services\Billing\InvoiceCalculator`. Tagihan terbit, baris tagihan, dan ledger deposit terkunci di level model; koreksi lewat void/refund (`BillingApprovals`, izin `Approve:Billing`, penyetuju ≠ pengaju). Uang masuk/keluar selalu tercatat ke sesi kasir yang terbuka (`CashSessions`).
- **SATUSEHAT:** semua panggilan lewat `App\Services\SatuSehat\SatuSehatClient` (token di-cache, error disaring). Jangan pernah mencatat URL/payload permintaan ke log: pencarian pasien memuat NIK. Kredensial hanya di `.env`. Pengiriman kunjungan lewat `VisitSync` (antrean, tidak dikirim dua kali).
- **Stok:** semua perubahan stok lewat `App\Services\Stock\StockLedger` (opname lewat `StockOpnames`); jangan mengubah `item_batches.qty_on_hand` langsung. Jumlah dalam satuan dasar, harga pokok per satuan dasar (bigint). Mutasi tercatat tidak bisa diubah; koreksi lewat pembatalan atau opname. Pemusnahan & opname butuh `Approve:Stock` (penyetuju ≠ pengaju).
- **Jasa dokter:** hanya lewat `App\Services\Commission\CommissionEngine` (aturan di-snapshot saat tagihan terbit; entri jasa tidak diubah, koreksi lewat entri pembalik) dan `CommissionPayouts` (`Manage:Commission`). Dokter tanpa `ViewAll:Commission` hanya melihat miliknya (`CommissionScope`).
- **KPI & laporan:** definisi KPI hanya di `App\Enums\Kpi`; angka harian lewat `App\Services\Reports\DailyMetrics`/`Metrics` (hari lampau dari snapshot `daily_metrics`, jangan diubah tanpa `--force`). Laporan baru = kelas di `app/Reports` + `ReportRegistry`; izin dicek di `canView()` dan berlaku sama untuk unduhan. Laba hanya `View:ProfitReports`.
- **Odontogram & OHI-S:** catatan dari peta lewat trait `EditsOdontogramChart` (satu baris per permukaan, kode seluruh gigi tanpa permukaan; catatan lama tidak ditimpa). Data peta/SVG hanya di `App\Services\Clinic\OdontogramVisual`, rumus OHI-S hanya di `App\Services\Clinic\OralHygiene`. Pemeriksaan intraoral terkunci bersama rekam medis.
- **Migrasi pasien lama** lewat `php artisan patients:migrate-legacy` (uji coba dulu, lalu `--commit`). Laporan tanpa nama/NIK. Jangan pernah menyalin data pasien asli ke repo, tes, atau artifact; tes memakai data fiktif.
- **Panduan pengguna** di `resources/guides/*.md` (menu Panduan). Perbarui bila alur atau label menu berubah. Cek rollout cabang: `BranchReadiness` (halaman Kesiapan cabang / `clinic:branch-readiness`).
- **Nomor urut** (RM, invoice, kunjungan, mutasi stok, order lab) wajib lewat `App\Services\NumberSequence`. RM yang diisi manual/migrasi memajukan urutan (`reserveRmNumber`).
- **Tampilan (Fondasi UI mockup v2, lihat `docs/12-perapian-ui.md`):** satu tombol teal per layar (aksi lain abu-abu); warna = arti (kuning perlu tindakan, hijau selesai/lunas, merah hanya peringatan medis & gagal); kode (No. RM, kunjungan, tagihan, ICD, gigi) pakai `.klinik-mono`/`fontFamily(FontFamily::Mono)`; uang di tabel `Money::format($x, false)` + `alignEnd()`, isian rupiah lewat `MoneyInput`; status janji/kunjungan titik + kata (`.klinik-status`); kolom sekunder `visibleFrom('md')` agar HP tetap terbaca. CSS tambahan hanya di `public/css/klinik.css` (tanpa build aset).
- **Lazy loading dilarang** di `local` & `testing` (`Model::shouldBeStrict`). Eager-load relasi di tabel dan relation manager.
- `app/Policies/*` dibuat oleh Shield. Jangan diedit manual; atur hak akses di seeder.
- **Service worker** (`public/sw.js`) hanya boleh meng-cache aset statis.
