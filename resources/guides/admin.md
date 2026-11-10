## Admin klinik

**Akun & akses** — *Organisasi & akses → Pengguna*: buat akun staf, pilih peran (owner, admin, perawat) dan cabang. Nonaktifkan akun staf yang keluar (jangan dihapus). **Reset MFA** bila HP staf hilang.

**Data barang** — *Master barang → Obat & bahan*: nama, kode, satuan dasar, konversi satuan (mis. 1 box = 50 pcs), stok minimum, kategori. Supplier di *Master barang → Supplier*. Data barang & supplier dari aplikasi inventori lama bisa dimasukkan lewat **Import CSV** di kedua halaman itu.

**Cabang** — *Organisasi & akses → Cabang*: nama, alamat, dan telepon (tercetak di kop berita acara & laporan). Stok, pesanan, dan kas pembelian dicatat per cabang.

**Persetujuan (4 mata)** — pemusnahan, koreksi opname, dan permintaan pembelian. Penyetuju tidak boleh orang yang mengajukan. Yang menunggu tampil di *Dashboard* (tombol **Tinjau persetujuan** dan daftar **Perlu tindakan**).

**Pembelian barang** — menu *Stok*:
- *Permintaan*: **Setujui** atau **Tolak** permintaan perawat. Centang yang sudah disetujui → **Buat pesanan**.
- *Pesanan*: **Buat pesanan** (supplier, barang, harga, perkiraan datang). Memesan tidak menambah stok; stok bertambah saat **Terima barang**. Bayar dari kas pembelian lewat **Catat pembayaran** (atau centang "Sudah dibayar" saat membuat pesanan).
- Supplier tidak mengirim sisanya: **Lainnya → Tutup sisa pesanan**. Belum datang sama sekali dan batal: **Batalkan pesanan** (uang yang sudah dibayar kembali ke kas).
- *Kas pembelian*: **Setor kas** dari owner, **Catat pengeluaran** untuk ongkir/beli langsung. Salah catat dikoreksi dengan **Koreksi** (entri asli tetap ada). Saldo tidak bisa minus.

**Mulai memakai aplikasi** — isi data barang & supplier (atau import), lalu **Mulai opname** di tiap cabang: hasil opname pertama menjadi stok awal.
