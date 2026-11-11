-- =====================================================================
--  HIBISCUS FOOTWEAR
--  File 03 : File Report (VIEW + STORED PROCEDURE)
--
--  Laporan tidak disimpan sebagai tabel, tetapi dihasilkan dari
--  pengolahan data master & transaksi.
--
--  Laporan                       VIEW                                PROCEDURE (filter periode)
--  ----------------------------  ----------------------------------  ---------------------------
--  1. Laporan Pembelian          v_laporan_pembelian                 sp_laporan_pembelian
--                                v_rekap_pembelian_bulanan
--  2. Laporan Stok Bahan Baku    v_laporan_stok_bahan_baku           -
--  3. Laporan Stok Produk Jadi   v_laporan_stok_produk               -
--  4. Laporan Produksi           v_laporan_produksi                  sp_laporan_produksi
--                                v_rekap_produksi_bulanan
--  5. Laporan Penjualan          v_laporan_penjualan                 sp_laporan_penjualan
--                                v_rekap_penjualan_bulanan
--  6. Laporan Produk Terlaris    v_produk_terlaris                   sp_produk_terlaris
--                                v_produk_terlaris_per_ukuran
--
--  CATATAN: setelah file 06_retur.sql dijalankan, laporan penjualan dan
--  produk terlaris diganti dengan versi yang memperhitungkan retur, dan
--  ditambah laporan ke-7 (Laporan Retur).
--
--  Penjualan yang dihitung sebagai omzet = status dibayar/dikirim/selesai
--  (pesanan belum dibayar dan yang dibatalkan TIDAK dihitung).
-- =====================================================================

USE hibiscus_footwear;

-- ---------------------------------------------------------------------
--  1. LAPORAN PEMBELIAN
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_laporan_pembelian AS
SELECT pb.id_pembelian,
       pb.no_faktur,
       pb.tanggal,
       s.id_supplier,
       s.nama_supplier,
       bb.kode_bahan,
       bb.nama_bahan,
       dp.jumlah,
       bb.satuan,
       dp.harga_satuan,
       dp.subtotal
  FROM pembelian pb
  JOIN supplier s          ON s.id_supplier = pb.id_supplier
  JOIN detail_pembelian dp ON dp.id_pembelian = pb.id_pembelian
  JOIN bahan_baku bb       ON bb.id_bahan = dp.id_bahan;

CREATE OR REPLACE VIEW v_rekap_pembelian_bulanan AS
SELECT DATE_FORMAT(pb.tanggal, '%Y-%m') AS periode,
       s.nama_supplier,
       COUNT(*)                         AS jumlah_transaksi,
       SUM(pb.total)                    AS total_pembelian
  FROM pembelian pb
  JOIN supplier s ON s.id_supplier = pb.id_supplier
 GROUP BY DATE_FORMAT(pb.tanggal, '%Y-%m'), s.id_supplier, s.nama_supplier;

-- ---------------------------------------------------------------------
--  2. LAPORAN STOK BAHAN BAKU
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_laporan_stok_bahan_baku AS
SELECT bb.kode_bahan,
       bb.nama_bahan,
       bb.satuan,
       bb.stok,
       bb.stok_minimum,
       bb.harga_terakhir,
       ROUND(bb.stok * COALESCE(bb.harga_terakhir, 0), 2) AS nilai_persediaan,
       CASE
         WHEN bb.stok = 0                THEN 'HABIS'
         WHEN bb.stok <= bb.stok_minimum THEN 'MENIPIS'
         ELSE 'AMAN'
       END AS status_stok
  FROM bahan_baku bb;

-- ---------------------------------------------------------------------
--  3. LAPORAN STOK PRODUK JADI (per ukuran)
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_laporan_stok_produk AS
SELECT p.kode_produk,
       p.nama_produk,
       k.nama_kategori,
       pu.ukuran,
       pu.sku,
       pu.stok,
       pu.stok_minimum,
       p.harga_jual,
       pu.stok * p.harga_jual AS nilai_persediaan,
       CASE
         WHEN pu.stok = 0                THEN 'HABIS'
         WHEN pu.stok <= pu.stok_minimum THEN 'MENIPIS'
         ELSE 'AMAN'
       END AS status_stok
  FROM produk_ukuran pu
  JOIN produk p   ON p.id_produk = pu.id_produk
  JOIN kategori k ON k.id_kategori = p.id_kategori;

-- ---------------------------------------------------------------------
--  4. LAPORAN PRODUKSI
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_laporan_produksi AS
SELECT pr.id_produksi,
       pr.no_produksi,
       p.kode_produk,
       p.nama_produk,
       pr.tanggal_mulai,
       pr.tanggal_selesai,
       pr.status,
       COALESCE(h.total_pasang, 0)            AS total_hasil_pasang,
       h.rincian_ukuran,
       COALESCE(b.jenis_bahan, 0)             AS jenis_bahan_dipakai,
       pg.nama_lengkap                        AS penanggung_jawab
  FROM produksi pr
  JOIN produk p    ON p.id_produk = pr.id_produk
  JOIN pengguna pg ON pg.id_pengguna = pr.id_pengguna
  LEFT JOIN (SELECT hp.id_produksi,
                    SUM(hp.jumlah) AS total_pasang,
                    GROUP_CONCAT(CONCAT(pu.ukuran, ':', hp.jumlah)
                                 ORDER BY pu.ukuran SEPARATOR ', ') AS rincian_ukuran
               FROM hasil_produksi hp
               JOIN produk_ukuran pu ON pu.id_varian = hp.id_varian
              GROUP BY hp.id_produksi) h ON h.id_produksi = pr.id_produksi
  LEFT JOIN (SELECT id_produksi, COUNT(*) AS jenis_bahan
               FROM pemakaian_bahan_baku
              GROUP BY id_produksi) b ON b.id_produksi = pr.id_produksi;

CREATE OR REPLACE VIEW v_rekap_produksi_bulanan AS
SELECT DATE_FORMAT(pr.tanggal_selesai, '%Y-%m') AS periode,
       p.nama_produk,
       COUNT(DISTINCT pr.id_produksi)           AS jumlah_batch,
       SUM(hp.jumlah)                           AS total_pasang
  FROM produksi pr
  JOIN produk p          ON p.id_produk = pr.id_produk
  JOIN hasil_produksi hp ON hp.id_produksi = pr.id_produksi
 WHERE pr.status = 'selesai'
 GROUP BY DATE_FORMAT(pr.tanggal_selesai, '%Y-%m'), p.id_produk, p.nama_produk;

-- ---------------------------------------------------------------------
--  5. LAPORAN PENJUALAN
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_laporan_penjualan AS
SELECT pj.id_penjualan,
       pj.no_pesanan,
       pj.tanggal_pesan,
       pl.nama_pelanggan,
       pl.kota,
       pj.status,
       (SELECT SUM(dp.jumlah) FROM detail_penjualan dp
         WHERE dp.id_penjualan = pj.id_penjualan) AS jumlah_pasang,
       pj.subtotal,
       pj.ongkir,
       pj.total,
       pb.metode       AS metode_bayar,
       pb.tanggal_bayar,
       pg.kurir,
       pg.no_resi,
       pg.tanggal_terima
  FROM penjualan pj
  JOIN pelanggan pl       ON pl.id_pelanggan = pj.id_pelanggan
  LEFT JOIN pembayaran pb ON pb.id_penjualan = pj.id_penjualan
  LEFT JOIN pengiriman pg ON pg.id_penjualan = pj.id_penjualan;

CREATE OR REPLACE VIEW v_rekap_penjualan_bulanan AS
SELECT DATE_FORMAT(pj.tanggal_pesan, '%Y-%m') AS periode,
       COUNT(*)                               AS jumlah_transaksi,
       SUM((SELECT SUM(dp.jumlah) FROM detail_penjualan dp
             WHERE dp.id_penjualan = pj.id_penjualan)) AS total_pasang,
       SUM(pj.subtotal)                       AS penjualan_produk,
       SUM(pj.ongkir)                         AS total_ongkir,
       SUM(pj.total)                          AS total_omzet
  FROM penjualan pj
 WHERE pj.status IN ('dibayar','dikirim','selesai')
 GROUP BY DATE_FORMAT(pj.tanggal_pesan, '%Y-%m');

-- ---------------------------------------------------------------------
--  6. LAPORAN PRODUK TERLARIS
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_produk_terlaris AS
SELECT RANK() OVER (ORDER BY SUM(dp.jumlah) DESC) AS peringkat,
       p.kode_produk,
       p.nama_produk,
       k.nama_kategori,
       SUM(dp.jumlah)   AS total_terjual,
       SUM(dp.subtotal) AS total_pendapatan
  FROM detail_penjualan dp
  JOIN penjualan pj     ON pj.id_penjualan = dp.id_penjualan
  JOIN produk_ukuran pu ON pu.id_varian = dp.id_varian
  JOIN produk p         ON p.id_produk = pu.id_produk
  JOIN kategori k       ON k.id_kategori = p.id_kategori
 WHERE pj.status IN ('dibayar','dikirim','selesai')
 GROUP BY p.id_produk, p.kode_produk, p.nama_produk, k.nama_kategori;

CREATE OR REPLACE VIEW v_produk_terlaris_per_ukuran AS
SELECT RANK() OVER (ORDER BY SUM(dp.jumlah) DESC) AS peringkat,
       p.nama_produk,
       pu.ukuran,
       pu.sku,
       SUM(dp.jumlah)   AS total_terjual,
       SUM(dp.subtotal) AS total_pendapatan
  FROM detail_penjualan dp
  JOIN penjualan pj     ON pj.id_penjualan = dp.id_penjualan
  JOIN produk_ukuran pu ON pu.id_varian = dp.id_varian
  JOIN produk p         ON p.id_produk = pu.id_produk
 WHERE pj.status IN ('dibayar','dikirim','selesai')
 GROUP BY pu.id_varian, p.nama_produk, pu.ukuran, pu.sku;

-- ---------------------------------------------------------------------
--  STORED PROCEDURE: laporan dengan filter periode
--  Contoh: CALL sp_laporan_penjualan('2026-10-01', '2026-10-31');
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_laporan_pembelian;
DROP PROCEDURE IF EXISTS sp_laporan_produksi;
DROP PROCEDURE IF EXISTS sp_laporan_penjualan;
DROP PROCEDURE IF EXISTS sp_produk_terlaris;

DELIMITER $$

-- p_id_supplier = NULL berarti semua supplier
CREATE PROCEDURE sp_laporan_pembelian(IN p_awal DATE, IN p_akhir DATE, IN p_id_supplier INT)
BEGIN
  -- rincian
  SELECT no_faktur, tanggal, nama_supplier, nama_bahan, jumlah, satuan, harga_satuan, subtotal
    FROM v_laporan_pembelian
   WHERE tanggal BETWEEN p_awal AND p_akhir
     AND (p_id_supplier IS NULL OR id_supplier = p_id_supplier)
   ORDER BY tanggal, no_faktur, nama_bahan;

  -- rekap per supplier
  SELECT s.nama_supplier,
         COUNT(*)      AS jumlah_transaksi,
         SUM(pb.total) AS total_pembelian
    FROM pembelian pb
    JOIN supplier s ON s.id_supplier = pb.id_supplier
   WHERE pb.tanggal BETWEEN p_awal AND p_akhir
     AND (p_id_supplier IS NULL OR pb.id_supplier = p_id_supplier)
   GROUP BY s.id_supplier, s.nama_supplier
   ORDER BY total_pembelian DESC;
END$$

CREATE PROCEDURE sp_laporan_produksi(IN p_awal DATE, IN p_akhir DATE)
BEGIN
  SELECT no_produksi, nama_produk, tanggal_mulai, tanggal_selesai, status,
         total_hasil_pasang, rincian_ukuran, jenis_bahan_dipakai, penanggung_jawab
    FROM v_laporan_produksi
   WHERE tanggal_mulai BETWEEN p_awal AND p_akhir
   ORDER BY tanggal_mulai, no_produksi;

  -- bahan baku yang terpakai pada periode tersebut
  SELECT bb.nama_bahan, SUM(pk.jumlah) AS total_dipakai, bb.satuan
    FROM pemakaian_bahan_baku pk
    JOIN produksi pr  ON pr.id_produksi = pk.id_produksi
    JOIN bahan_baku bb ON bb.id_bahan = pk.id_bahan
   WHERE pr.tanggal_mulai BETWEEN p_awal AND p_akhir
   GROUP BY bb.id_bahan, bb.nama_bahan, bb.satuan
   ORDER BY bb.nama_bahan;
END$$

CREATE PROCEDURE sp_laporan_penjualan(IN p_awal DATE, IN p_akhir DATE)
BEGIN
  -- rincian semua pesanan pada periode (termasuk status)
  SELECT no_pesanan, tanggal_pesan, nama_pelanggan, status,
         jumlah_pasang, subtotal, ongkir, total, metode_bayar, no_resi
    FROM v_laporan_penjualan
   WHERE DATE(tanggal_pesan) BETWEEN p_awal AND p_akhir
   ORDER BY tanggal_pesan;

  -- ringkasan omzet (hanya pesanan yang sudah dibayar)
  SELECT COUNT(*)                     AS jumlah_transaksi,
         COALESCE(SUM(jumlah_pasang),0) AS total_pasang,
         COALESCE(SUM(subtotal),0)    AS penjualan_produk,
         COALESCE(SUM(ongkir),0)      AS total_ongkir,
         COALESCE(SUM(total),0)       AS total_omzet
    FROM v_laporan_penjualan
   WHERE DATE(tanggal_pesan) BETWEEN p_awal AND p_akhir
     AND status IN ('dibayar','dikirim','selesai');
END$$

CREATE PROCEDURE sp_produk_terlaris(IN p_awal DATE, IN p_akhir DATE, IN p_limit INT)
BEGIN
  SELECT RANK() OVER (ORDER BY SUM(dp.jumlah) DESC) AS peringkat,
         p.nama_produk,
         SUM(dp.jumlah)   AS total_terjual,
         SUM(dp.subtotal) AS total_pendapatan
    FROM detail_penjualan dp
    JOIN penjualan pj     ON pj.id_penjualan = dp.id_penjualan
    JOIN produk_ukuran pu ON pu.id_varian = dp.id_varian
    JOIN produk p         ON p.id_produk = pu.id_produk
   WHERE pj.status IN ('dibayar','dikirim','selesai')
     AND DATE(pj.tanggal_pesan) BETWEEN p_awal AND p_akhir
   GROUP BY p.id_produk, p.nama_produk
   ORDER BY total_terjual DESC
   LIMIT p_limit;
END$$

DELIMITER ;
