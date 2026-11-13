-- =====================================================================
--  HIBISCUS FOOTWEAR
--  File 04 : Data contoh (dummy) untuk uji coba & demo laporan
--
--  Data transaksi dimasukkan berurutan seperti alur bisnis nyata, dan
--  SEMUA stok dihitung otomatis oleh trigger (tidak diisi manual):
--    pembelian -> produksi (pakai bahan, hasil per ukuran) -> penjualan
--    -> pembayaran -> pengiriman -> diterima
-- =====================================================================

USE hibiscus_footwear;

-- ---------------------------------------------------------------------
--  MASTER
-- ---------------------------------------------------------------------
INSERT INTO kategori (id_kategori, nama_kategori, deskripsi) VALUES
 (1, 'Sneakers',   'Sepatu kasual bersol karet'),
 (2, 'Heels',      'Sepatu wanita berhak'),
 (3, 'Flat Shoes', 'Sepatu wanita tanpa hak'),
 (4, 'Sandal',     'Alas kaki terbuka');

INSERT INTO produk (id_produk, kode_produk, nama_produk, id_kategori, deskripsi, harga_jual) VALUES
 (1, 'SNK-001', 'Hibiscus Runner', 1, 'Sneakers kulit sintetis, sol karet empuk', 349000),
 (2, 'SNK-002', 'Bloom Canvas',    1, 'Sneakers kanvas ringan',                    279000),
 (3, 'HLS-001', 'Rosa Heels 7cm',  2, 'Heels pesta hak 7 cm',                      399000),
 (4, 'FLT-001', 'Petal Flat',      3, 'Flat shoes kerja',                          229000),
 (5, 'SDL-001', 'Tropica Sandal',  4, 'Sandal tali kulit sintetis',                159000);

-- Varian ukuran 36-40 untuk setiap produk (stok awal 0, terisi dari produksi)
INSERT INTO produk_ukuran (id_varian, id_produk, ukuran, sku, stok_minimum) VALUES
 ( 1, 1, 36, 'SNK-001-36', 3), ( 2, 1, 37, 'SNK-001-37', 3), ( 3, 1, 38, 'SNK-001-38', 3),
 ( 4, 1, 39, 'SNK-001-39', 3), ( 5, 1, 40, 'SNK-001-40', 3),
 ( 6, 2, 36, 'SNK-002-36', 3), ( 7, 2, 37, 'SNK-002-37', 3), ( 8, 2, 38, 'SNK-002-38', 3),
 ( 9, 2, 39, 'SNK-002-39', 3), (10, 2, 40, 'SNK-002-40', 3),
 (11, 3, 36, 'HLS-001-36', 3), (12, 3, 37, 'HLS-001-37', 3), (13, 3, 38, 'HLS-001-38', 3),
 (14, 3, 39, 'HLS-001-39', 3), (15, 3, 40, 'HLS-001-40', 3),
 (16, 4, 36, 'FLT-001-36', 3), (17, 4, 37, 'FLT-001-37', 3), (18, 4, 38, 'FLT-001-38', 3),
 (19, 4, 39, 'FLT-001-39', 3), (20, 4, 40, 'FLT-001-40', 3),
 (21, 5, 36, 'SDL-001-36', 3), (22, 5, 37, 'SDL-001-37', 3), (23, 5, 38, 'SDL-001-38', 3),
 (24, 5, 39, 'SDL-001-39', 3), (25, 5, 40, 'SDL-001-40', 3);

INSERT INTO bahan_baku (id_bahan, kode_bahan, nama_bahan, satuan, stok_minimum) VALUES
 (1, 'BB-001', 'Kulit sintetis',     'meter',  20),
 (2, 'BB-002', 'Kain kanvas',        'meter',  15),
 (3, 'BB-003', 'Sol karet',          'pasang', 40),
 (4, 'BB-004', 'Lem sepatu',         'liter',   5),
 (5, 'BB-005', 'Benang jahit',       'gulung',  5),
 (6, 'BB-006', 'Tali sepatu',        'pasang', 30),
 (7, 'BB-007', 'Insole busa',        'pasang', 30),
 (8, 'BB-008', 'Kotak kemasan',      'pcs',    50);

INSERT INTO supplier (id_supplier, nama_supplier, kontak_person, no_telepon, email, alamat, kota) VALUES
 (1, 'PT Kulit Nusantara',     'Budi Santoso',  '021-5550101', 'sales@kulitnusantara.example', 'Jl. Industri No. 12',     'Tangerang'),
 (2, 'CV Sol Jaya',            'Siti Rahma',    '031-5550202', 'order@soljaya.example',        'Jl. Rungkut Industri 5',  'Surabaya'),
 (3, 'Toko Aksesoris Makmur',  'Hendra Wijaya', '022-5550303', 'makmur@aksesoris.example',     'Jl. Cibaduyut Raya 88',   'Bandung');

INSERT INTO pelanggan (id_pelanggan, nama_pelanggan, email, no_hp, alamat, kota, kode_pos) VALUES
 (1, 'Anisa Putri',     'anisa.putri@mail.example',  '081200000001', 'Jl. Melati No. 3',      'Jakarta Selatan', '12430'),
 (2, 'Rizky Pratama',   'rizky.p@mail.example',      '081200000002', 'Jl. Kenanga No. 17',    'Bandung',         '40115'),
 (3, 'Dewi Lestari',    'dewi.l@mail.example',       '081200000003', 'Jl. Mawar Blok C/9',    'Surabaya',        '60231'),
 (4, 'Fajar Nugroho',   'fajar.n@mail.example',      '081200000004', 'Jl. Anggrek No. 21',    'Yogyakarta',      '55281'),
 (5, 'Maya Sari',       'maya.sari@mail.example',    '081200000005', 'Jl. Teratai No. 45',    'Semarang',        '50241');

-- Hash di bawah adalah SHA2-256 dari password DEMO 'hibiscus123'.
-- Untuk aplikasi sungguhan gunakan password_hash()/bcrypt dari bahasa pemrograman.
INSERT INTO pengguna (id_pengguna, username, password_hash, nama_lengkap, peran) VALUES
 (1, 'admin',     SHA2('hibiscus123', 256), 'Administrator',     'admin'),
 (2, 'gudang',    SHA2('hibiscus123', 256), 'Rina (Gudang)',     'gudang'),
 (3, 'produksi',  SHA2('hibiscus123', 256), 'Agus (Produksi)',   'produksi'),
 (4, 'penjualan', SHA2('hibiscus123', 256), 'Laras (Penjualan)', 'penjualan');

-- ---------------------------------------------------------------------
--  TRANSAKSI - SEPTEMBER 2026
-- ---------------------------------------------------------------------
-- Pembelian bahan baku (stok bahan bertambah otomatis)
INSERT INTO pembelian (id_pembelian, no_faktur, tanggal, id_supplier, id_pengguna) VALUES
 (1, 'PB-2026-09-001', '2026-09-02', 1, 2),
 (2, 'PB-2026-09-002', '2026-09-03', 2, 2),
 (3, 'PB-2026-09-003', '2026-09-04', 3, 2);

INSERT INTO detail_pembelian (id_pembelian, id_bahan, jumlah, harga_satuan) VALUES
 (1, 1, 120, 45000),   -- kulit sintetis 120 m
 (1, 7, 200,  6000),   -- insole 200 pasang
 (2, 3, 250, 18000),   -- sol karet 250 pasang
 (2, 4,  25, 55000),   -- lem 25 liter
 (3, 5,  30, 12000),   -- benang 30 gulung
 (3, 6, 150,  3000),   -- tali 150 pasang
 (3, 8, 250,  4500);   -- kotak 250 pcs

-- Produksi #1: Hibiscus Runner (50 pasang)
INSERT INTO produksi (id_produksi, no_produksi, id_produk, tanggal_mulai, id_pengguna) VALUES
 (1, 'PR-2026-09-001', 1, '2026-09-05', 3);
INSERT INTO pemakaian_bahan_baku (id_produksi, id_bahan, jumlah) VALUES
 (1, 1, 40), (1, 3, 50), (1, 4, 5), (1, 5, 6), (1, 6, 50), (1, 7, 50), (1, 8, 50);
INSERT INTO hasil_produksi (id_produksi, id_varian, jumlah) VALUES
 (1, 1, 8), (1, 2, 10), (1, 3, 12), (1, 4, 10), (1, 5, 10);
UPDATE produksi SET status = 'selesai', tanggal_selesai = '2026-09-12' WHERE id_produksi = 1;

-- Produksi #2: Rosa Heels (40 pasang)
INSERT INTO produksi (id_produksi, no_produksi, id_produk, tanggal_mulai, id_pengguna) VALUES
 (2, 'PR-2026-09-002', 3, '2026-09-14', 3);
INSERT INTO pemakaian_bahan_baku (id_produksi, id_bahan, jumlah) VALUES
 (2, 1, 30), (2, 3, 40), (2, 4, 4), (2, 5, 4), (2, 7, 40), (2, 8, 40);
INSERT INTO hasil_produksi (id_produksi, id_varian, jumlah) VALUES
 (2, 11, 8), (2, 12, 10), (2, 13, 10), (2, 14, 8), (2, 15, 4);
UPDATE produksi SET status = 'selesai', tanggal_selesai = '2026-09-20' WHERE id_produksi = 2;

-- Penjualan September
-- Catatan: total tagihan dibaca dulu ke variabel @tagihan karena trigger pembayaran
-- akan mengubah tabel penjualan (MySQL tidak mengizinkan INSERT ... SELECT FROM penjualan).
INSERT INTO penjualan (id_penjualan, no_pesanan, tanggal_pesan, id_pelanggan, alamat_kirim, ongkir) VALUES
 (1, 'ORD-20260922-001', '2026-09-22 10:15:00', 1, 'Jl. Melati No. 3, Jakarta Selatan 12430', 15000),
 (2, 'ORD-20260928-001', '2026-09-28 19:40:00', 2, 'Jl. Kenanga No. 17, Bandung 40115',       20000);
-- harga_satuan NULL -> diisi otomatis dari harga produk saat ini (snapshot)
INSERT INTO detail_penjualan (id_penjualan, id_varian, jumlah, harga_satuan) VALUES
 (1,  3, 1, NULL),     -- Runner 38
 (1, 12, 1, NULL),     -- Rosa 37
 (2, 13, 2, NULL);     -- Rosa 38 x2

SELECT total INTO @tagihan FROM penjualan WHERE id_penjualan = 1;
INSERT INTO pembayaran (id_penjualan, tanggal_bayar, metode, jumlah_bayar, no_referensi) VALUES (1, '2026-09-22 11:00:00', 'transfer_bank', @tagihan, 'TRF-0922-001');
SELECT total INTO @tagihan FROM penjualan WHERE id_penjualan = 2;
INSERT INTO pembayaran (id_penjualan, tanggal_bayar, metode, jumlah_bayar, no_referensi) VALUES (2, '2026-09-28 20:05:00', 'e_wallet', @tagihan, 'EW-0928-001');

INSERT INTO pengiriman (id_penjualan, kurir, layanan, no_resi, tanggal_kirim, id_pengguna) VALUES
 (1, 'JNE',      'REG', 'JNE0000000001', '2026-09-23', 4),
 (2, 'SiCepat',  'BEST','SCP0000000001', '2026-09-29', 4);
UPDATE pengiriman SET tanggal_terima = '2026-09-25' WHERE id_penjualan = 1;
UPDATE pengiriman SET tanggal_terima = '2026-09-30' WHERE id_penjualan = 2;

-- ---------------------------------------------------------------------
--  TRANSAKSI - OKTOBER 2026
-- ---------------------------------------------------------------------
INSERT INTO pembelian (id_pembelian, no_faktur, tanggal, id_supplier, id_pengguna) VALUES
 (4, 'PB-2026-10-001', '2026-10-01', 3, 2);
INSERT INTO detail_pembelian (id_pembelian, id_bahan, jumlah, harga_satuan) VALUES
 (4, 2, 60, 30000);    -- kain kanvas 60 m

-- Produksi #3: Bloom Canvas (60 pasang)
INSERT INTO produksi (id_produksi, no_produksi, id_produk, tanggal_mulai, id_pengguna) VALUES
 (3, 'PR-2026-10-001', 2, '2026-10-02', 3);
INSERT INTO pemakaian_bahan_baku (id_produksi, id_bahan, jumlah) VALUES
 (3, 2, 45), (3, 3, 60), (3, 4, 5), (3, 5, 8), (3, 6, 60), (3, 7, 60), (3, 8, 60);
INSERT INTO hasil_produksi (id_produksi, id_varian, jumlah) VALUES
 (3, 6, 10), (3, 7, 12), (3, 8, 14), (3, 9, 14), (3, 10, 10);
UPDATE produksi SET status = 'selesai', tanggal_selesai = '2026-10-08' WHERE id_produksi = 3;

-- Produksi #4: Tropica Sandal (40 pasang)
INSERT INTO produksi (id_produksi, no_produksi, id_produk, tanggal_mulai, id_pengguna) VALUES
 (4, 'PR-2026-10-002', 5, '2026-10-10', 3);
INSERT INTO pemakaian_bahan_baku (id_produksi, id_bahan, jumlah) VALUES
 (4, 1, 20), (4, 3, 40), (4, 4, 3), (4, 5, 3), (4, 8, 40);
INSERT INTO hasil_produksi (id_produksi, id_varian, jumlah) VALUES
 (4, 21, 6), (4, 22, 8), (4, 23, 10), (4, 24, 10), (4, 25, 6);
UPDATE produksi SET status = 'selesai', tanggal_selesai = '2026-10-15' WHERE id_produksi = 4;

INSERT INTO penjualan (id_penjualan, no_pesanan, tanggal_pesan, id_pelanggan, alamat_kirim, ongkir) VALUES
 (3, 'ORD-20261009-001', '2026-10-09 08:30:00', 3, 'Jl. Mawar Blok C/9, Surabaya 60231',  18000),
 (4, 'ORD-20261018-001', '2026-10-18 13:20:00', 4, 'Jl. Anggrek No. 21, Yogyakarta 55281', 22000),
 (5, 'ORD-20261025-001', '2026-10-25 21:10:00', 1, 'Jl. Melati No. 3, Jakarta Selatan 12430', 15000);
INSERT INTO detail_penjualan (id_penjualan, id_varian, jumlah, harga_satuan) VALUES
 (3,  9, 2, NULL),     -- Bloom 39 x2
 (3,  5, 1, NULL),     -- Runner 40
 (4, 23, 3, NULL),     -- Sandal 38 x3
 (4,  7, 1, NULL),     -- Bloom 37
 (5,  3, 2, NULL),     -- Runner 38 x2
 (5,  8, 1, NULL);     -- Bloom 38

SELECT total INTO @tagihan FROM penjualan WHERE id_penjualan = 3;
INSERT INTO pembayaran (id_penjualan, tanggal_bayar, metode, jumlah_bayar, no_referensi) VALUES (3, '2026-10-09 09:00:00', 'e_wallet', @tagihan, 'EW-1009-001');
SELECT total INTO @tagihan FROM penjualan WHERE id_penjualan = 4;
INSERT INTO pembayaran (id_penjualan, tanggal_bayar, metode, jumlah_bayar, no_referensi) VALUES (4, '2026-10-18 14:00:00', 'transfer_bank', @tagihan, 'TRF-1018-001');
SELECT total INTO @tagihan FROM penjualan WHERE id_penjualan = 5;
INSERT INTO pembayaran (id_penjualan, tanggal_bayar, metode, jumlah_bayar, no_referensi) VALUES (5, '2026-10-25 21:30:00', 'kartu_kredit', @tagihan, 'CC-1025-001');

INSERT INTO pengiriman (id_penjualan, kurir, layanan, no_resi, tanggal_kirim, id_pengguna) VALUES
 (3, 'JNE',     'YES', 'JNE0000000002', '2026-10-10', 4),
 (4, 'J&T',     'EZ',  'JNT0000000001', '2026-10-19', 4),
 (5, 'SiCepat', 'REG', 'SCP0000000002', '2026-10-27', 4);
UPDATE pengiriman SET tanggal_terima = '2026-10-11' WHERE id_penjualan = 3;
UPDATE pengiriman SET tanggal_terima = '2026-10-22' WHERE id_penjualan = 4;
-- pesanan #5 masih dalam perjalanan (status: dikirim)

INSERT INTO pembelian (id_pembelian, no_faktur, tanggal, id_supplier, id_pengguna) VALUES
 (5, 'PB-2026-10-002', '2026-10-20', 1, 2);
INSERT INTO detail_pembelian (id_pembelian, id_bahan, jumlah, harga_satuan) VALUES
 (5, 1, 50, 46000);    -- kulit sintetis 50 m (harga naik)

-- ---------------------------------------------------------------------
--  TRANSAKSI - NOVEMBER 2026
-- ---------------------------------------------------------------------
-- Produksi #5: Petal Flat - MASIH PROSES (bahan sudah dipakai, hasil belum ada)
INSERT INTO produksi (id_produksi, no_produksi, id_produk, tanggal_mulai, id_pengguna, catatan) VALUES
 (5, 'PR-2026-11-001', 4, '2026-11-05', 3, 'Target 30 pasang');
INSERT INTO pemakaian_bahan_baku (id_produksi, id_bahan, jumlah) VALUES
 (5, 1, 25), (5, 3, 30), (5, 4, 3), (5, 7, 30);

INSERT INTO penjualan (id_penjualan, no_pesanan, tanggal_pesan, id_pelanggan, alamat_kirim, ongkir) VALUES
 (6, 'ORD-20261102-001', '2026-11-02 16:45:00', 5, 'Jl. Teratai No. 45, Semarang 50241',  20000),
 (7, 'ORD-20261108-001', '2026-11-08 11:05:00', 2, 'Jl. Kenanga No. 17, Bandung 40115',   20000),
 (8, 'ORD-20261110-001', '2026-11-10 07:55:00', 3, 'Jl. Mawar Blok C/9, Surabaya 60231',  18000);
INSERT INTO detail_penjualan (id_penjualan, id_varian, jumlah, harga_satuan) VALUES
 (6,  4, 1, NULL),     -- Runner 39
 (7, 11, 1, NULL),     -- Rosa 36
 (7, 22, 2, NULL),     -- Sandal 37 x2
 (8,  6, 1, NULL);     -- Bloom 36

-- #6 sudah dibayar, belum dikirim
SELECT total INTO @tagihan FROM penjualan WHERE id_penjualan = 6;
INSERT INTO pembayaran (id_penjualan, tanggal_bayar, metode, jumlah_bayar, no_referensi) VALUES (6, '2026-11-02 17:00:00', 'e_wallet', @tagihan, 'EW-1102-001');

-- #7 dibatalkan pelanggan sebelum membayar -> stok dikembalikan otomatis
UPDATE penjualan SET status = 'dibatalkan', catatan = 'Dibatalkan pelanggan' WHERE id_penjualan = 7;

-- #8 masih menunggu pembayaran
