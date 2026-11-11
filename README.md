# HIBISCUS FOOTWEAR

Database **Sistem Informasi Penjualan dan Produksi HIBISCUS FOOTWEAR**, sebuah brand sepatu yang memproduksi sendiri dan berjualan online.

- 7 tabel master, 11 tabel transaksi, dan 7 laporan (VIEW + STORED PROCEDURE)
- Stok bahan baku dan stok produk jadi **per ukuran** diperbarui otomatis oleh trigger
- Aturan bisnis dijaga langsung oleh database: pembayaran harus lunas, stok tidak boleh minus, dan status pesanan tidak bisa melompat
- Retur barang (refund atau tukar ukuran) maksimal 7 hari setelah barang diterima

Rancangan lengkap (daftar entitas, kamus data, ERD, alur proses) ada di **[docs/Rancangan_Database.md](docs/Rancangan_Database.md)**.

## Struktur Folder

```
HIBISCUS_FOOTWEAR/
├── README.md
├── docs/
│   └── Rancangan_Database.md   # rancangan, kamus data, ERD
└── database/
    ├── 01_schema.sql             # membuat database & 16 tabel
    ├── 02_triggers.sql           # aturan bisnis & stok otomatis
    ├── 03_laporan.sql            # VIEW & PROCEDURE laporan
    ├── 04_data_contoh.sql        # data dummy Sep–Nov 2026
    ├── 06_retur.sql              # tabel & aturan retur, laporan diperbarui
    ├── 07_data_contoh_retur.sql  # data dummy retur
    └── 08_uji_coba.sql           # (opsional) tampilkan laporan + uji aturan
```

## Kebutuhan

- XAMPP (MariaDB 10.4+) atau MySQL 8.0+

## Cara Menjalankan

> ⚠️ `01_schema.sql` akan **menghapus** database `hibiscus_footwear` (jika sudah ada), lalu membuatnya ulang.

### Opsi A: phpMyAdmin (XAMPP)

1. Buka XAMPP Control Panel, lalu **Start** Apache dan MySQL.
2. Buka `http://localhost/phpmyadmin`.
3. Pilih tab **Import**, lalu impor file di folder `database/` **berurutan**: `01` → `02` → `03` → `04` → `06` → `07`.
4. **Opsional:** impor `08_uji_coba.sql` untuk melihat semua laporan dan hasil pengujian. File ini tidak mengubah data.

> Sudah pernah impor `01`–`04`? Cukup impor `06` lalu `07`, karena data lama tidak dihapus.
> `06` aman diimpor ulang. `07` cukup sekali; jika diimpor ulang akan berhenti di error *Duplicate entry* tanpa mengubah data.

### Opsi B: Command line

Dari folder `database/` (sesuaikan path `mysql` dan password root):

```bash
C:/xampp/mysql/bin/mysql -u root < 01_schema.sql
C:/xampp/mysql/bin/mysql -u root < 02_triggers.sql
C:/xampp/mysql/bin/mysql -u root < 03_laporan.sql
C:/xampp/mysql/bin/mysql -u root < 04_data_contoh.sql
C:/xampp/mysql/bin/mysql -u root < 06_retur.sql
C:/xampp/mysql/bin/mysql -u root < 07_data_contoh_retur.sql
C:/xampp/mysql/bin/mysql -u root -t < 08_uji_coba.sql
```

## Contoh Penggunaan Laporan

```sql
USE hibiscus_footwear;

SELECT * FROM v_laporan_stok_bahan_baku;                  -- stok bahan baku
SELECT * FROM v_laporan_stok_produk;                      -- stok produk per ukuran
SELECT * FROM v_rekap_penjualan_bulanan;                  -- omzet per bulan
SELECT * FROM v_produk_terlaris;                          -- produk terlaris

CALL sp_laporan_pembelian('2026-09-01', '2026-09-30', NULL);  -- NULL = semua supplier
CALL sp_laporan_produksi('2026-10-01', '2026-10-31');
CALL sp_laporan_penjualan('2026-10-01', '2026-10-31');
CALL sp_produk_terlaris('2026-09-01', '2026-11-30', 3);
CALL sp_laporan_retur('2026-10-01', '2026-10-31');
```

## Contoh Alur Transaksi

```sql
-- 1. Pelanggan memesan (stok berkurang otomatis, harga diambil dari produk)
INSERT INTO penjualan (no_pesanan, id_pelanggan, alamat_kirim, ongkir)
VALUES ('ORD-20261111-001', 1, 'Jl. Melati No. 3, Jakarta Selatan', 15000);
SET @id = LAST_INSERT_ID();
INSERT INTO detail_penjualan (id_penjualan, id_varian, jumlah, harga_satuan)
VALUES (@id, 3, 1, NULL);

-- 2. Pembayaran lunas (status otomatis menjadi 'dibayar')
SELECT total INTO @tagihan FROM penjualan WHERE id_penjualan = @id;
INSERT INTO pembayaran (id_penjualan, metode, jumlah_bayar) VALUES (@id, 'qris', @tagihan);

-- 3. Pengiriman (status otomatis 'dikirim'), lalu diterima (status 'selesai')
INSERT INTO pengiriman (id_penjualan, kurir, no_resi, tanggal_kirim, id_pengguna)
VALUES (@id, 'JNE', 'JNE123456789', CURDATE(), 4);
UPDATE pengiriman SET tanggal_terima = CURDATE() WHERE id_penjualan = @id;

-- 4. Retur (maks 7 hari setelah diterima): ajukan -> diterima gudang -> selesai
INSERT INTO retur (no_retur, id_penjualan, tanggal_pengajuan, jenis, alasan)
VALUES ('RT-20261111-001', @id, CURDATE(), 'tukar', 'Ukuran kekecilan');
SET @rt = LAST_INSERT_ID();
INSERT INTO detail_retur (id_retur, id_varian, kondisi, jumlah, harga_satuan, id_varian_pengganti)
VALUES (@rt, 3, 'baik', 1, NULL, 4);              -- tukar ukuran 38 -> 39
UPDATE retur SET status = 'diterima', id_pengguna = 2 WHERE id_retur = @rt;  -- stok diproses
UPDATE retur SET status = 'selesai' WHERE id_retur = @rt;
```

## Hasil Pengujian

`08_uji_coba.sql` sudah dijalankan di MariaDB 10.4.32 (XAMPP), dan semua uji lolos:

- 24 dari 24 uji aturan bisnis **ditolak dengan benar**, misalnya pesanan melebihi stok, pembayaran tidak lunas, kirim sebelum bayar, lompat status, retur lewat 7 hari, dan retur melebihi jumlah dibeli
- Stok tersimpan **sama** dengan stok hasil hitung ulang dari semua transaksi (bahan baku dan produk jadi)
- Total pembelian dan penjualan **sama** dengan jumlah subtotal detailnya
- Skenario lengkap (pesan → bayar → kirim → diterima → retur refund, dan menyelesaikan produksi) berjalan, lalu di-rollback sehingga data contoh tidak berubah
