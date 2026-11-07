## Admin klinik

**Akun & akses** — *Pengaturan & Akses → Pengguna*: buat akun staf, pilih peran dan cabang. Nonaktifkan akun staf yang keluar (jangan dihapus). **Reset MFA** bila HP staf hilang.

**Master** — *Master Klinis → Tindakan & tarif* (tarif khusus cabang di tab *Tarif per cabang*), *Master barang → Obat & bahan*, BOM, supplier & lab, template persetujuan. Data barang & supplier dari aplikasi inventori lama bisa dimasukkan lewat **Import CSV** di halaman *Obat & bahan* dan *Supplier & lab*.

**Tenaga kesehatan** — *Organisasi → Tenaga kesehatan*: SIP & masa berlakunya, NIK, ID IHS SATUSEHAT, jadwal praktik, cuti & libur.

**Persetujuan (4 mata)** — pemusnahan dan opname stok, permintaan pembelian, void/refund tagihan. Penyetuju tidak boleh orang yang mengajukan.

**Pembelian barang** — menu *Stok*:
- *Permintaan*: **Setujui** atau **Tolak** permintaan perawat. Centang yang sudah disetujui → **Buat pesanan**.
- *Pesanan*: **Buat pesanan** (supplier, barang, harga, perkiraan datang). Memesan tidak menambah stok; stok bertambah saat **Terima barang**. Bayar dari kas pembelian lewat **Catat pembayaran** (atau centang "Sudah dibayar" saat membuat pesanan).
- Supplier tidak mengirim sisanya: **Lainnya → Tutup sisa pesanan**. Belum datang sama sekali dan batal: **Batalkan pesanan** (uang yang sudah dibayar kembali ke kas).
- *Kas pembelian*: **Setor kas** dari owner, **Catat pengeluaran** untuk ongkir/beli langsung. Salah catat dikoreksi dengan **Koreksi** (entri asli tetap ada). Saldo tidak bisa minus.

**Membuka cabang baru** — *Organisasi → Kesiapan cabang*. Selesaikan semua butir **Wajib** (akun, kursi, jadwal, SIP, tarif, aturan jasa, SATUSEHAT). Lalu lakukan opname awal stok.

**Migrasi data pasien lama** — dijalankan oleh tim IT di server: `php artisan patients:migrate-legacy <berkas.xlsx>` (uji coba), periksa laporan, lalu ulangi dengan `--commit`.
