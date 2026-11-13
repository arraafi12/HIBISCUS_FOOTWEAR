-- =====================================================================
--  SISTEM INFORMASI PENJUALAN DAN PRODUKSI HIBISCUS FOOTWEAR
--  File 01 : Struktur database (tabel master & transaksi)
--  DBMS    : MySQL 8.0+ / MariaDB 10.4+ (XAMPP)
--
--  PERINGATAN: skrip ini MENGHAPUS database `hibiscus_footwear`
--  (jika sudah ada) lalu membuatnya ulang dari awal.
-- =====================================================================

DROP DATABASE IF EXISTS hibiscus_footwear;
CREATE DATABASE hibiscus_footwear
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE hibiscus_footwear;

-- =====================================================================
--  A. FILE MASTER
-- =====================================================================

-- M1. Kategori: pengelompokan jenis sepatu
CREATE TABLE kategori (
  id_kategori    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nama_kategori  VARCHAR(50)   NOT NULL,
  deskripsi      VARCHAR(255)  NULL,
  PRIMARY KEY (id_kategori),
  UNIQUE KEY uq_kategori_nama (nama_kategori)
) ENGINE=InnoDB;

-- M2. Produk: model sepatu yang diproduksi & dijual
CREATE TABLE produk (
  id_produk      INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  kode_produk    VARCHAR(20)    NOT NULL,
  nama_produk    VARCHAR(100)   NOT NULL,
  id_kategori    INT UNSIGNED   NOT NULL,
  deskripsi      TEXT           NULL,
  harga_jual     DECIMAL(12,2)  NOT NULL,
  aktif          TINYINT(1)     NOT NULL DEFAULT 1,
  dibuat_pada    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_produk),
  UNIQUE KEY uq_produk_kode (kode_produk),
  CONSTRAINT fk_produk_kategori FOREIGN KEY (id_kategori)
    REFERENCES kategori (id_kategori) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_produk_harga CHECK (harga_jual >= 0)
) ENGINE=InnoDB;

-- M3. Produk Ukuran (varian/SKU): stok produk jadi PER UKURAN
CREATE TABLE produk_ukuran (
  id_varian      INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  id_produk      INT UNSIGNED      NOT NULL,
  ukuran         TINYINT UNSIGNED  NOT NULL,
  sku            VARCHAR(30)       NOT NULL,
  stok           INT               NOT NULL DEFAULT 0,
  stok_minimum   INT               NOT NULL DEFAULT 5,
  PRIMARY KEY (id_varian),
  UNIQUE KEY uq_varian_sku (sku),
  UNIQUE KEY uq_varian_produk_ukuran (id_produk, ukuran),
  CONSTRAINT fk_varian_produk FOREIGN KEY (id_produk)
    REFERENCES produk (id_produk) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_varian_ukuran CHECK (ukuran BETWEEN 30 AND 50),
  CONSTRAINT chk_varian_stok   CHECK (stok >= 0),
  CONSTRAINT chk_varian_stokmin CHECK (stok_minimum >= 0)
) ENGINE=InnoDB;

-- M4. Bahan Baku: stok bahan (terpisah dari stok produk jadi)
CREATE TABLE bahan_baku (
  id_bahan       INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  kode_bahan     VARCHAR(20)    NOT NULL,
  nama_bahan     VARCHAR(100)   NOT NULL,
  satuan         VARCHAR(15)    NOT NULL,          -- meter, pasang, liter, gulung, pcs, ...
  stok           DECIMAL(12,2)  NOT NULL DEFAULT 0,
  stok_minimum   DECIMAL(12,2)  NOT NULL DEFAULT 0,
  harga_terakhir DECIMAL(12,2)  NULL,               -- diisi otomatis dari pembelian terakhir
  PRIMARY KEY (id_bahan),
  UNIQUE KEY uq_bahan_kode (kode_bahan),
  CONSTRAINT chk_bahan_stok    CHECK (stok >= 0),
  CONSTRAINT chk_bahan_stokmin CHECK (stok_minimum >= 0)
) ENGINE=InnoDB;

-- M5. Supplier: pemasok bahan baku
CREATE TABLE supplier (
  id_supplier    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nama_supplier  VARCHAR(100)  NOT NULL,
  kontak_person  VARCHAR(100)  NULL,
  no_telepon     VARCHAR(20)   NOT NULL,
  email          VARCHAR(100)  NULL,
  alamat         VARCHAR(255)  NOT NULL,
  kota           VARCHAR(50)   NOT NULL,
  aktif          TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (id_supplier)
) ENGINE=InnoDB;

-- M6. Pelanggan: pembeli di website
CREATE TABLE pelanggan (
  id_pelanggan   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nama_pelanggan VARCHAR(100)  NOT NULL,
  email          VARCHAR(100)  NOT NULL,
  no_hp          VARCHAR(20)   NOT NULL,
  alamat         VARCHAR(255)  NOT NULL,
  kota           VARCHAR(50)   NOT NULL,
  kode_pos       VARCHAR(10)   NULL,
  terdaftar_pada TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pelanggan),
  UNIQUE KEY uq_pelanggan_email (email)
) ENGINE=InnoDB;

-- M7. Pengguna: admin/pegawai yang login ke sistem
CREATE TABLE pengguna (
  id_pengguna    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  username       VARCHAR(30)   NOT NULL,
  password_hash  VARCHAR(255)  NOT NULL,          -- simpan HASH, bukan password asli
  nama_lengkap   VARCHAR(100)  NOT NULL,
  peran          ENUM('admin','gudang','produksi','penjualan') NOT NULL,
  aktif          TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (id_pengguna),
  UNIQUE KEY uq_pengguna_username (username)
) ENGINE=InnoDB;

-- =====================================================================
--  B. FILE TRANSAKSI
-- =====================================================================

-- T1. Pembelian (header): pembelian bahan baku dari supplier
CREATE TABLE pembelian (
  id_pembelian   INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  no_faktur      VARCHAR(30)    NOT NULL,
  tanggal        DATE           NOT NULL,
  id_supplier    INT UNSIGNED   NOT NULL,
  id_pengguna    INT UNSIGNED   NOT NULL,           -- petugas yang mencatat
  total          DECIMAL(14,2)  NOT NULL DEFAULT 0, -- dihitung otomatis dari detail
  keterangan     VARCHAR(255)   NULL,
  PRIMARY KEY (id_pembelian),
  UNIQUE KEY uq_pembelian_faktur (no_faktur),
  KEY idx_pembelian_tanggal (tanggal),
  CONSTRAINT fk_pembelian_supplier FOREIGN KEY (id_supplier)
    REFERENCES supplier (id_supplier) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_pembelian_pengguna FOREIGN KEY (id_pengguna)
    REFERENCES pengguna (id_pengguna) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- T2. Detail Pembelian: rincian bahan baku per pembelian
CREATE TABLE detail_pembelian (
  id_pembelian   INT UNSIGNED   NOT NULL,
  id_bahan       INT UNSIGNED   NOT NULL,
  jumlah         DECIMAL(12,2)  NOT NULL,
  harga_satuan   DECIMAL(12,2)  NOT NULL,
  subtotal       DECIMAL(14,2)  AS (jumlah * harga_satuan) STORED,
  PRIMARY KEY (id_pembelian, id_bahan),
  CONSTRAINT fk_dpb_pembelian FOREIGN KEY (id_pembelian)
    REFERENCES pembelian (id_pembelian) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_dpb_bahan FOREIGN KEY (id_bahan)
    REFERENCES bahan_baku (id_bahan) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_dpb_jumlah CHECK (jumlah > 0),
  CONSTRAINT chk_dpb_harga  CHECK (harga_satuan >= 0)
) ENGINE=InnoDB;

-- T3. Produksi (header): satu batch produksi untuk satu model produk
CREATE TABLE produksi (
  id_produksi     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  no_produksi     VARCHAR(30)   NOT NULL,
  id_produk       INT UNSIGNED  NOT NULL,
  tanggal_mulai   DATE          NOT NULL,
  tanggal_selesai DATE          NULL,
  status          ENUM('proses','selesai') NOT NULL DEFAULT 'proses',
  id_pengguna     INT UNSIGNED  NOT NULL,           -- penanggung jawab produksi
  catatan         VARCHAR(255)  NULL,
  PRIMARY KEY (id_produksi),
  UNIQUE KEY uq_produksi_no (no_produksi),
  KEY idx_produksi_tanggal (tanggal_mulai),
  CONSTRAINT fk_produksi_produk FOREIGN KEY (id_produk)
    REFERENCES produk (id_produk) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_produksi_pengguna FOREIGN KEY (id_pengguna)
    REFERENCES pengguna (id_pengguna) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_produksi_tanggal
    CHECK (tanggal_selesai IS NULL OR tanggal_selesai >= tanggal_mulai)
) ENGINE=InnoDB;

-- T4. Pemakaian Bahan Baku: bahan yang dipakai pada sebuah produksi
CREATE TABLE pemakaian_bahan_baku (
  id_produksi    INT UNSIGNED   NOT NULL,
  id_bahan       INT UNSIGNED   NOT NULL,
  jumlah         DECIMAL(12,2)  NOT NULL,
  PRIMARY KEY (id_produksi, id_bahan),
  CONSTRAINT fk_pakai_produksi FOREIGN KEY (id_produksi)
    REFERENCES produksi (id_produksi) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_pakai_bahan FOREIGN KEY (id_bahan)
    REFERENCES bahan_baku (id_bahan) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_pakai_jumlah CHECK (jumlah > 0)
) ENGINE=InnoDB;

-- T5. Hasil Produksi: jumlah pasang yang dihasilkan PER UKURAN
CREATE TABLE hasil_produksi (
  id_produksi    INT UNSIGNED  NOT NULL,
  id_varian      INT UNSIGNED  NOT NULL,
  jumlah         INT           NOT NULL,
  PRIMARY KEY (id_produksi, id_varian),
  CONSTRAINT fk_hasil_produksi FOREIGN KEY (id_produksi)
    REFERENCES produksi (id_produksi) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_hasil_varian FOREIGN KEY (id_varian)
    REFERENCES produk_ukuran (id_varian) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_hasil_jumlah CHECK (jumlah > 0)
) ENGINE=InnoDB;

-- T6. Penjualan (header pesanan online)
CREATE TABLE penjualan (
  id_penjualan   INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  no_pesanan     VARCHAR(30)    NOT NULL,
  tanggal_pesan  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_pelanggan   INT UNSIGNED   NOT NULL,
  status         ENUM('menunggu_pembayaran','dibayar','dikirim','selesai','dibatalkan')
                                NOT NULL DEFAULT 'menunggu_pembayaran',
  alamat_kirim   VARCHAR(255)   NOT NULL,           -- snapshot alamat saat memesan
  subtotal       DECIMAL(14,2)  NOT NULL DEFAULT 0, -- dihitung otomatis dari detail
  ongkir         DECIMAL(12,2)  NOT NULL DEFAULT 0,
  total          DECIMAL(14,2)  AS (subtotal + ongkir) STORED,
  catatan        VARCHAR(255)   NULL,
  PRIMARY KEY (id_penjualan),
  UNIQUE KEY uq_penjualan_no (no_pesanan),
  KEY idx_penjualan_tanggal (tanggal_pesan),
  KEY idx_penjualan_status (status),
  CONSTRAINT fk_penjualan_pelanggan FOREIGN KEY (id_pelanggan)
    REFERENCES pelanggan (id_pelanggan) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_penjualan_ongkir CHECK (ongkir >= 0)
) ENGINE=InnoDB;

-- T7. Detail Penjualan: produk + ukuran + jumlah + harga saat transaksi
CREATE TABLE detail_penjualan (
  id_penjualan   INT UNSIGNED   NOT NULL,
  id_varian      INT UNSIGNED   NOT NULL,
  jumlah         INT            NOT NULL,
  harga_satuan   DECIMAL(12,2)  NOT NULL,           -- snapshot harga; otomatis jika diisi NULL
  subtotal       DECIMAL(14,2)  AS (jumlah * harga_satuan) STORED,
  PRIMARY KEY (id_penjualan, id_varian),
  CONSTRAINT fk_dpj_penjualan FOREIGN KEY (id_penjualan)
    REFERENCES penjualan (id_penjualan) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_dpj_varian FOREIGN KEY (id_varian)
    REFERENCES produk_ukuran (id_varian) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_dpj_jumlah CHECK (jumlah > 0),
  CONSTRAINT chk_dpj_harga  CHECK (harga_satuan >= 0)
) ENGINE=InnoDB;

-- T8. Pembayaran: satu kali, lunas (1 pesanan = 1 pembayaran)
CREATE TABLE pembayaran (
  id_pembayaran  INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  id_penjualan   INT UNSIGNED   NOT NULL,
  tanggal_bayar  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  metode         ENUM('transfer_bank','e_wallet','kartu_kredit') NOT NULL,
  jumlah_bayar   DECIMAL(14,2)  NOT NULL,
  no_referensi   VARCHAR(50)    NULL,
  bukti_bayar    MEDIUMBLOB     NULL,
  tipe_bukti     VARCHAR(30)    NULL,
  PRIMARY KEY (id_pembayaran),
  UNIQUE KEY uq_pembayaran_penjualan (id_penjualan),
  CONSTRAINT fk_pembayaran_penjualan FOREIGN KEY (id_penjualan)
    REFERENCES penjualan (id_penjualan) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- T9. Pengiriman: kurir, resi, tanggal kirim & terima
CREATE TABLE pengiriman (
  id_pengiriman  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  id_penjualan   INT UNSIGNED  NOT NULL,
  kurir          VARCHAR(30)   NOT NULL,
  layanan        VARCHAR(30)   NULL,
  no_resi        VARCHAR(50)   NOT NULL,
  tanggal_kirim  DATE          NOT NULL,
  tanggal_terima DATE          NULL,
  id_pengguna    INT UNSIGNED  NOT NULL,            -- petugas yang mengirim
  PRIMARY KEY (id_pengiriman),
  UNIQUE KEY uq_pengiriman_penjualan (id_penjualan),
  UNIQUE KEY uq_pengiriman_resi (kurir, no_resi),
  CONSTRAINT fk_pengiriman_penjualan FOREIGN KEY (id_penjualan)
    REFERENCES penjualan (id_penjualan) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_pengiriman_pengguna FOREIGN KEY (id_pengguna)
    REFERENCES pengguna (id_pengguna) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_pengiriman_tanggal
    CHECK (tanggal_terima IS NULL OR tanggal_terima >= tanggal_kirim)
) ENGINE=InnoDB;
