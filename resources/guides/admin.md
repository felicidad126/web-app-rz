## Admin klinik

**Akun & akses** — *Pengaturan & Akses → Pengguna*: buat akun staf, pilih peran dan cabang. Nonaktifkan akun staf yang keluar (jangan dihapus). **Reset MFA** bila HP staf hilang.

**Master** — *Master Klinis → Tindakan & tarif* (tarif khusus cabang di tab *Tarif per cabang*), *Master barang → Obat & bahan*, BOM, supplier & lab, template persetujuan.

**Tenaga kesehatan** — *Organisasi → Tenaga kesehatan*: SIP & masa berlakunya, NIK, ID IHS SATUSEHAT, jadwal praktik, cuti & libur.

**Persetujuan (4 mata)** — pemusnahan dan opname stok, void/refund tagihan. Penyetuju tidak boleh orang yang mengajukan.

**Membuka cabang baru** — *Organisasi → Kesiapan cabang*. Selesaikan semua butir **Wajib** (akun, kursi, jadwal, SIP, tarif, aturan jasa, SATUSEHAT). Lalu lakukan opname awal stok.

**Migrasi data pasien lama** — dijalankan oleh tim IT di server: `php artisan patients:migrate-legacy <berkas.xlsx>` (uji coba), periksa laporan, lalu ulangi dengan `--commit`.
