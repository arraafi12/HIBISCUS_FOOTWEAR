-- =====================================================================
--  HIBISCUS FOOTWEAR
--  File 06 : Retur barang (tabel, trigger, dan pembaruan laporan)
--  Jalankan SETELAH file 01-04. Aman dijalankan ulang: tabel retur
--  yang sudah ada TIDAK dihapus (hanya trigger, view, dan procedure
--  yang dibuat ulang), sehingga stok tetap konsisten.
--
--  Aturan retur:
--   - Hanya pesanan berstatus 'selesai', diajukan paling lambat 7 hari
--     setelah tanggal barang diterima pelanggan.
--   - Jenis retur: 'refund' (uang kembali) atau 'tukar' (ganti ukuran
--     pada produk yang sama).
--   - Jumlah yang diretur tidak boleh melebihi jumlah yang dibeli
--     (dihitung kumulatif dari semua retur yang tidak ditolak).
--   - Alur status: diajukan -> diterima | ditolak ; diterima -> selesai
--   - Saat retur 'diterima' (barang sudah sampai di gudang):
--       kondisi 'baik'  -> stok ukuran tersebut BERTAMBAH
--       kondisi 'cacat' -> stok TIDAK bertambah (hanya dicatat)
--       jenis 'tukar'   -> stok ukuran pengganti BERKURANG
--   - Saat retur 'selesai': refund sudah ditransfer / barang pengganti
--     sudah dikirim. Refund mengurangi omzet pada bulan retur selesai.
-- =====================================================================

USE hibiscus_footwear;

-- ---------------------------------------------------------------------
--  T10. RETUR (header)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS retur (
  id_retur          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  no_retur          VARCHAR(30)    NOT NULL,
  id_penjualan      INT UNSIGNED   NOT NULL,
  tanggal_pengajuan DATE           NOT NULL,
  jenis             ENUM('refund','tukar') NOT NULL,
  alasan            VARCHAR(255)   NOT NULL,
  status            ENUM('diajukan','diterima','ditolak','selesai') NOT NULL DEFAULT 'diajukan',
  total_refund      DECIMAL(14,2)  NOT NULL DEFAULT 0,  -- dihitung otomatis dari detail
  tanggal_selesai   DATE           NULL,
  id_pengguna       INT UNSIGNED   NULL,                -- petugas yang memeriksa barang
  catatan           VARCHAR(255)   NULL,
  PRIMARY KEY (id_retur),
  UNIQUE KEY uq_retur_no (no_retur),
  KEY idx_retur_tanggal (tanggal_pengajuan),
  CONSTRAINT fk_retur_penjualan FOREIGN KEY (id_penjualan)
    REFERENCES penjualan (id_penjualan) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_retur_pengguna FOREIGN KEY (id_pengguna)
    REFERENCES pengguna (id_pengguna) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_retur_tanggal
    CHECK (tanggal_selesai IS NULL OR tanggal_selesai >= tanggal_pengajuan),
  CONSTRAINT chk_retur_refund CHECK (total_refund >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  T11. DETAIL RETUR: produk/ukuran yang dikembalikan
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS detail_retur (
  id_retur            INT UNSIGNED   NOT NULL,
  id_varian           INT UNSIGNED   NOT NULL,          -- ukuran yang dikembalikan
  kondisi             ENUM('baik','cacat') NOT NULL,
  jumlah              INT            NOT NULL,
  harga_satuan        DECIMAL(12,2)  NOT NULL,          -- otomatis = harga saat dibeli
  subtotal            DECIMAL(14,2)  AS (jumlah * harga_satuan) STORED,
  id_varian_pengganti INT UNSIGNED   NULL,              -- wajib diisi jika jenis 'tukar'
  PRIMARY KEY (id_retur, id_varian, kondisi),
  CONSTRAINT fk_dretur_retur FOREIGN KEY (id_retur)
    REFERENCES retur (id_retur) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_dretur_varian FOREIGN KEY (id_varian)
    REFERENCES produk_ukuran (id_varian) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_dretur_pengganti FOREIGN KEY (id_varian_pengganti)
    REFERENCES produk_ukuran (id_varian) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_dretur_jumlah CHECK (jumlah > 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  TRIGGER RETUR
-- ---------------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_retur_bi;
DROP TRIGGER IF EXISTS trg_retur_bu;
DROP TRIGGER IF EXISTS trg_retur_au;
DROP TRIGGER IF EXISTS trg_retur_bd;
DROP TRIGGER IF EXISTS trg_detail_retur_bi;
DROP TRIGGER IF EXISTS trg_detail_retur_ai;
DROP TRIGGER IF EXISTS trg_detail_retur_bu;
DROP TRIGGER IF EXISTS trg_detail_retur_bd;
DROP TRIGGER IF EXISTS trg_detail_retur_ad;

DELIMITER $$

CREATE TRIGGER trg_retur_bi
BEFORE INSERT ON retur FOR EACH ROW
BEGIN
  DECLARE v_status  VARCHAR(30);
  DECLARE v_terima  DATE;
  DECLARE v_msg     VARCHAR(255);

  IF NEW.status <> 'diajukan' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Retur baru harus berstatus diajukan.';
  END IF;

  SET v_status = (SELECT status FROM penjualan WHERE id_penjualan = NEW.id_penjualan);
  SET v_terima = (SELECT tanggal_terima FROM pengiriman WHERE id_penjualan = NEW.id_penjualan);

  IF v_status <> 'selesai' OR v_terima IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Retur hanya bisa diajukan untuk pesanan yang sudah selesai (diterima pelanggan).';
  END IF;

  IF NEW.tanggal_pengajuan < v_terima
     OR NEW.tanggal_pengajuan > DATE_ADD(v_terima, INTERVAL 7 DAY) THEN
    SET v_msg = CONCAT('Batas pengajuan retur 7 hari setelah barang diterima (diterima ',
                       v_terima, ', batas ', DATE_ADD(v_terima, INTERVAL 7 DAY), ').');
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
  END IF;

  SET NEW.total_refund = 0;
  SET NEW.tanggal_selesai = NULL;
END$$

CREATE TRIGGER trg_retur_bu
BEFORE UPDATE ON retur FOR EACH ROW
BEGIN
  DECLARE v_sku VARCHAR(30);
  DECLARE v_msg VARCHAR(255);

  IF NEW.id_penjualan <> OLD.id_penjualan OR NEW.jenis <> OLD.jenis
     OR NEW.tanggal_pengajuan <> OLD.tanggal_pengajuan THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Pesanan, jenis, dan tanggal pengajuan retur tidak boleh diubah.';
  END IF;

  IF NEW.status <> OLD.status THEN
    IF NOT (   (OLD.status = 'diajukan' AND NEW.status IN ('diterima','ditolak'))
            OR (OLD.status = 'diterima' AND NEW.status = 'selesai')) THEN
      SET v_msg = CONCAT('Perubahan status retur dari "', OLD.status, '" ke "',
                         NEW.status, '" tidak diizinkan.');
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    IF NEW.status = 'diterima' THEN
      IF NOT EXISTS (SELECT 1 FROM detail_retur WHERE id_retur = OLD.id_retur) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Retur belum memiliki detail barang.';
      END IF;
      IF NEW.id_pengguna IS NULL THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'Isi id_pengguna (petugas pemeriksa) saat menerima retur.';
      END IF;
      -- stok ukuran pengganti harus cukup
      SET v_sku = (SELECT pu.sku
                     FROM (SELECT id_varian_pengganti AS id_varian, SUM(jumlah) AS butuh
                             FROM detail_retur
                            WHERE id_retur = OLD.id_retur AND id_varian_pengganti IS NOT NULL
                            GROUP BY id_varian_pengganti) x
                     JOIN produk_ukuran pu ON pu.id_varian = x.id_varian
                    WHERE pu.stok < x.butuh
                    LIMIT 1);
      IF v_sku IS NOT NULL THEN
        SET v_msg = CONCAT('Stok ukuran pengganti ', v_sku, ' tidak cukup untuk penukaran.');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
      END IF;
    END IF;

    IF NEW.status = 'selesai' AND NEW.tanggal_selesai IS NULL THEN
      SET NEW.tanggal_selesai = CURDATE();
    END IF;
  END IF;
END$$

CREATE TRIGGER trg_retur_au
AFTER UPDATE ON retur FOR EACH ROW
BEGIN
  IF OLD.status = 'diajukan' AND NEW.status = 'diterima' THEN
    -- barang kondisi baik kembali ke stok jual
    UPDATE produk_ukuran pu
      JOIN (SELECT id_varian, SUM(jumlah) AS jml
              FROM detail_retur
             WHERE id_retur = NEW.id_retur AND kondisi = 'baik'
             GROUP BY id_varian) x ON x.id_varian = pu.id_varian
       SET pu.stok = pu.stok + x.jml;

    -- barang pengganti (tukar ukuran) keluar dari stok
    UPDATE produk_ukuran pu
      JOIN (SELECT id_varian_pengganti AS id_varian, SUM(jumlah) AS jml
              FROM detail_retur
             WHERE id_retur = NEW.id_retur AND id_varian_pengganti IS NOT NULL
             GROUP BY id_varian_pengganti) x ON x.id_varian = pu.id_varian
       SET pu.stok = pu.stok - x.jml;
  END IF;
END$$

CREATE TRIGGER trg_retur_bd
BEFORE DELETE ON retur FOR EACH ROW
BEGIN
  IF OLD.status <> 'diajukan' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Retur yang sudah diproses tidak boleh dihapus.';
  END IF;
END$$

CREATE TRIGGER trg_detail_retur_bi
BEFORE INSERT ON detail_retur FOR EACH ROW
BEGIN
  DECLARE v_status      VARCHAR(20);
  DECLARE v_jenis       VARCHAR(10);
  DECLARE v_penjualan   INT UNSIGNED;
  DECLARE v_dibeli      INT;
  DECLARE v_harga       DECIMAL(12,2);
  DECLARE v_sudah       INT;
  DECLARE v_produk      INT UNSIGNED;
  DECLARE v_produk_ganti INT UNSIGNED;
  DECLARE v_msg         VARCHAR(255);

  SELECT status, jenis, id_penjualan INTO v_status, v_jenis, v_penjualan
    FROM retur WHERE id_retur = NEW.id_retur;

  IF v_status <> 'diajukan' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Barang hanya bisa ditambahkan pada retur berstatus diajukan.';
  END IF;

  SET v_dibeli = (SELECT jumlah FROM detail_penjualan
                   WHERE id_penjualan = v_penjualan AND id_varian = NEW.id_varian);
  SET v_harga  = (SELECT harga_satuan FROM detail_penjualan
                   WHERE id_penjualan = v_penjualan AND id_varian = NEW.id_varian);
  IF v_dibeli IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Produk/ukuran ini tidak ada di pesanan yang diretur.';
  END IF;

  SET v_sudah = (SELECT COALESCE(SUM(dr.jumlah), 0)
                   FROM detail_retur dr JOIN retur r ON r.id_retur = dr.id_retur
                  WHERE r.id_penjualan = v_penjualan AND r.status <> 'ditolak'
                    AND dr.id_varian = NEW.id_varian);
  IF v_sudah + NEW.jumlah > v_dibeli THEN
    SET v_msg = CONCAT('Jumlah retur melebihi pembelian: dibeli ', v_dibeli,
                       ' pasang, sudah diretur ', v_sudah, ', diajukan ', NEW.jumlah, '.');
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
  END IF;

  IF v_jenis = 'tukar' THEN
    IF NEW.id_varian_pengganti IS NULL THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Retur jenis tukar wajib mengisi id_varian_pengganti.';
    END IF;
    SET v_produk       = (SELECT id_produk FROM produk_ukuran WHERE id_varian = NEW.id_varian);
    SET v_produk_ganti = (SELECT id_produk FROM produk_ukuran WHERE id_varian = NEW.id_varian_pengganti);
    IF v_produk <> v_produk_ganti THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Penukaran hanya boleh dengan ukuran lain dari produk yang sama.';
    END IF;
  ELSEIF NEW.id_varian_pengganti IS NOT NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Retur jenis refund tidak memakai id_varian_pengganti.';
  END IF;

  -- nilai barang mengikuti harga saat dibeli, bukan harga sekarang
  SET NEW.harga_satuan = v_harga;
END$$

CREATE TRIGGER trg_detail_retur_ai
AFTER INSERT ON detail_retur FOR EACH ROW
BEGIN
  UPDATE retur
     SET total_refund = total_refund + (NEW.jumlah * NEW.harga_satuan)
   WHERE id_retur = NEW.id_retur AND jenis = 'refund';
END$$

CREATE TRIGGER trg_detail_retur_bu
BEFORE UPDATE ON detail_retur FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Detail retur tidak boleh diubah. Hapus baris lalu input ulang.';
END$$

CREATE TRIGGER trg_detail_retur_bd
BEFORE DELETE ON detail_retur FOR EACH ROW
BEGIN
  IF (SELECT status FROM retur WHERE id_retur = OLD.id_retur) <> 'diajukan' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Detail retur yang sudah diproses tidak boleh dihapus.';
  END IF;
END$$

CREATE TRIGGER trg_detail_retur_ad
AFTER DELETE ON detail_retur FOR EACH ROW
BEGIN
  UPDATE retur
     SET total_refund = total_refund - (OLD.jumlah * OLD.harga_satuan)
   WHERE id_retur = OLD.id_retur AND jenis = 'refund';
END$$

DELIMITER ;

-- =====================================================================
--  LAPORAN RETUR (baru)
-- =====================================================================
CREATE OR REPLACE VIEW v_laporan_retur AS
SELECT r.id_retur,
       r.no_retur,
       r.tanggal_pengajuan,
       r.tanggal_selesai,
       r.status,
       r.jenis,
       pj.no_pesanan,
       pl.nama_pelanggan,
       p.nama_produk,
       pu.ukuran        AS ukuran_retur,
       dr.kondisi,
       dr.jumlah,
       dr.harga_satuan,
       dr.subtotal      AS nilai_barang,
       pg.ukuran        AS ukuran_pengganti,
       r.alasan
  FROM retur r
  JOIN penjualan pj       ON pj.id_penjualan = r.id_penjualan
  JOIN pelanggan pl       ON pl.id_pelanggan = pj.id_pelanggan
  JOIN detail_retur dr    ON dr.id_retur = r.id_retur
  JOIN produk_ukuran pu   ON pu.id_varian = dr.id_varian
  JOIN produk p           ON p.id_produk = pu.id_produk
  LEFT JOIN produk_ukuran pg ON pg.id_varian = dr.id_varian_pengganti;

CREATE OR REPLACE VIEW v_rekap_retur_bulanan AS
SELECT DATE_FORMAT(r.tanggal_pengajuan, '%Y-%m')                     AS periode,
       COUNT(DISTINCT r.id_retur)                                    AS jumlah_retur,
       COUNT(DISTINCT CASE WHEN r.jenis = 'refund' THEN r.id_retur END) AS retur_refund,
       COUNT(DISTINCT CASE WHEN r.jenis = 'tukar'  THEN r.id_retur END) AS retur_tukar,
       SUM(dr.jumlah)                                                AS pasang_diretur,
       SUM(CASE WHEN dr.kondisi = 'cacat' THEN dr.jumlah ELSE 0 END) AS pasang_cacat,
       SUM(CASE WHEN r.jenis = 'refund' AND r.status = 'selesai'
                THEN dr.subtotal ELSE 0 END)                         AS refund_dibayar
  FROM retur r
  JOIN detail_retur dr ON dr.id_retur = r.id_retur
 WHERE r.status <> 'ditolak'
 GROUP BY DATE_FORMAT(r.tanggal_pengajuan, '%Y-%m');

-- =====================================================================
--  PEMBARUAN LAPORAN PENJUALAN & PRODUK TERLARIS (memperhitungkan retur)
-- =====================================================================

-- Mutasi penjualan bersih per ukuran:
--   + barang terjual (pesanan dibayar/dikirim/selesai)
--   - barang diretur (retur diterima/selesai)
--   + barang pengganti hasil tukar ukuran
CREATE OR REPLACE VIEW v_mutasi_penjualan AS
SELECT dp.id_varian, DATE(pj.tanggal_pesan) AS tanggal, dp.jumlah AS jumlah, dp.subtotal AS pendapatan
  FROM detail_penjualan dp
  JOIN penjualan pj ON pj.id_penjualan = dp.id_penjualan
 WHERE pj.status IN ('dibayar','dikirim','selesai')
UNION ALL
SELECT dr.id_varian, r.tanggal_pengajuan, -dr.jumlah, -dr.subtotal
  FROM detail_retur dr
  JOIN retur r ON r.id_retur = dr.id_retur
 WHERE r.status IN ('diterima','selesai')
UNION ALL
SELECT dr.id_varian_pengganti, r.tanggal_pengajuan, dr.jumlah, dr.subtotal
  FROM detail_retur dr
  JOIN retur r ON r.id_retur = dr.id_retur
 WHERE r.status IN ('diterima','selesai') AND dr.id_varian_pengganti IS NOT NULL;

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
       (SELECT COALESCE(SUM(r.total_refund), 0) FROM retur r
         WHERE r.id_penjualan = pj.id_penjualan AND r.status = 'selesai') AS refund,
       pb.metode       AS metode_bayar,
       pb.tanggal_bayar,
       pg.kurir,
       pg.no_resi,
       pg.tanggal_terima
  FROM penjualan pj
  JOIN pelanggan pl       ON pl.id_pelanggan = pj.id_pelanggan
  LEFT JOIN pembayaran pb ON pb.id_penjualan = pj.id_penjualan
  LEFT JOIN pengiriman pg ON pg.id_penjualan = pj.id_penjualan;

-- Refund dicatat pada bulan retur selesai (uang dikembalikan)
CREATE OR REPLACE VIEW v_rekap_penjualan_bulanan AS
SELECT per.periode,
       COALESCE(j.jumlah_transaksi, 0)  AS jumlah_transaksi,
       COALESCE(j.total_pasang, 0)      AS total_pasang,
       COALESCE(j.penjualan_produk, 0)  AS penjualan_produk,
       COALESCE(j.total_ongkir, 0)      AS total_ongkir,
       COALESCE(j.total_omzet, 0)       AS total_omzet,
       COALESCE(r.total_refund, 0)      AS total_refund,
       COALESCE(j.total_omzet, 0) - COALESCE(r.total_refund, 0) AS omzet_bersih
  FROM (SELECT DATE_FORMAT(tanggal_pesan, '%Y-%m') AS periode
          FROM penjualan WHERE status IN ('dibayar','dikirim','selesai')
        UNION
        SELECT DATE_FORMAT(tanggal_selesai, '%Y-%m')
          FROM retur WHERE status = 'selesai' AND total_refund > 0) per
  LEFT JOIN (SELECT DATE_FORMAT(pj.tanggal_pesan, '%Y-%m') AS periode,
                    COUNT(*)          AS jumlah_transaksi,
                    SUM(d.pasang)     AS total_pasang,
                    SUM(pj.subtotal)  AS penjualan_produk,
                    SUM(pj.ongkir)    AS total_ongkir,
                    SUM(pj.total)     AS total_omzet
               FROM penjualan pj
               JOIN (SELECT id_penjualan, SUM(jumlah) AS pasang
                       FROM detail_penjualan GROUP BY id_penjualan) d
                 ON d.id_penjualan = pj.id_penjualan
              WHERE pj.status IN ('dibayar','dikirim','selesai')
              GROUP BY DATE_FORMAT(pj.tanggal_pesan, '%Y-%m')) j ON j.periode = per.periode
  LEFT JOIN (SELECT DATE_FORMAT(tanggal_selesai, '%Y-%m') AS periode,
                    SUM(total_refund) AS total_refund
               FROM retur WHERE status = 'selesai'
              GROUP BY DATE_FORMAT(tanggal_selesai, '%Y-%m')) r ON r.periode = per.periode;

CREATE OR REPLACE VIEW v_produk_terlaris AS
SELECT RANK() OVER (ORDER BY SUM(m.jumlah) DESC) AS peringkat,
       p.kode_produk,
       p.nama_produk,
       k.nama_kategori,
       SUM(m.jumlah)     AS total_terjual,
       SUM(m.pendapatan) AS total_pendapatan
  FROM v_mutasi_penjualan m
  JOIN produk_ukuran pu ON pu.id_varian = m.id_varian
  JOIN produk p         ON p.id_produk = pu.id_produk
  JOIN kategori k       ON k.id_kategori = p.id_kategori
 GROUP BY p.id_produk, p.kode_produk, p.nama_produk, k.nama_kategori;

CREATE OR REPLACE VIEW v_produk_terlaris_per_ukuran AS
SELECT RANK() OVER (ORDER BY SUM(m.jumlah) DESC) AS peringkat,
       p.nama_produk,
       pu.ukuran,
       pu.sku,
       SUM(m.jumlah)     AS total_terjual,
       SUM(m.pendapatan) AS total_pendapatan
  FROM v_mutasi_penjualan m
  JOIN produk_ukuran pu ON pu.id_varian = m.id_varian
  JOIN produk p         ON p.id_produk = pu.id_produk
 GROUP BY pu.id_varian, p.nama_produk, pu.ukuran, pu.sku
HAVING SUM(m.jumlah) > 0;

DROP PROCEDURE IF EXISTS sp_laporan_penjualan;
DROP PROCEDURE IF EXISTS sp_produk_terlaris;
DROP PROCEDURE IF EXISTS sp_laporan_retur;

DELIMITER $$

CREATE PROCEDURE sp_laporan_penjualan(IN p_awal DATE, IN p_akhir DATE)
BEGIN
  SELECT no_pesanan, tanggal_pesan, nama_pelanggan, status,
         jumlah_pasang, subtotal, ongkir, total, refund, metode_bayar, no_resi
    FROM v_laporan_penjualan
   WHERE DATE(tanggal_pesan) BETWEEN p_awal AND p_akhir
   ORDER BY tanggal_pesan;

  SELECT j.jumlah_transaksi, j.total_pasang, j.penjualan_produk, j.total_ongkir, j.total_omzet,
         r.total_refund,
         j.total_omzet - r.total_refund AS omzet_bersih
    FROM (SELECT COUNT(*)                       AS jumlah_transaksi,
                 COALESCE(SUM(jumlah_pasang),0) AS total_pasang,
                 COALESCE(SUM(subtotal),0)      AS penjualan_produk,
                 COALESCE(SUM(ongkir),0)        AS total_ongkir,
                 COALESCE(SUM(total),0)         AS total_omzet
            FROM v_laporan_penjualan
           WHERE DATE(tanggal_pesan) BETWEEN p_awal AND p_akhir
             AND status IN ('dibayar','dikirim','selesai')) j
   CROSS JOIN
         (SELECT COALESCE(SUM(total_refund),0) AS total_refund
            FROM retur
           WHERE status = 'selesai' AND tanggal_selesai BETWEEN p_awal AND p_akhir) r;
END$$

CREATE PROCEDURE sp_produk_terlaris(IN p_awal DATE, IN p_akhir DATE, IN p_limit INT)
BEGIN
  SELECT RANK() OVER (ORDER BY SUM(m.jumlah) DESC) AS peringkat,
         p.nama_produk,
         SUM(m.jumlah)     AS total_terjual,
         SUM(m.pendapatan) AS total_pendapatan
    FROM v_mutasi_penjualan m
    JOIN produk_ukuran pu ON pu.id_varian = m.id_varian
    JOIN produk p         ON p.id_produk = pu.id_produk
   WHERE m.tanggal BETWEEN p_awal AND p_akhir
   GROUP BY p.id_produk, p.nama_produk
   ORDER BY total_terjual DESC
   LIMIT p_limit;
END$$

CREATE PROCEDURE sp_laporan_retur(IN p_awal DATE, IN p_akhir DATE)
BEGIN
  SELECT no_retur, tanggal_pengajuan, status, jenis, no_pesanan, nama_pelanggan,
         nama_produk, ukuran_retur, kondisi, jumlah, nilai_barang, ukuran_pengganti, alasan
    FROM v_laporan_retur
   WHERE tanggal_pengajuan BETWEEN p_awal AND p_akhir
   ORDER BY tanggal_pengajuan, no_retur;

  SELECT COUNT(DISTINCT id_retur)                                AS jumlah_retur,
         COALESCE(SUM(jumlah), 0)                                AS pasang_diretur,
         COALESCE(SUM(CASE WHEN kondisi = 'cacat' THEN jumlah END), 0) AS pasang_cacat,
         COALESCE(SUM(CASE WHEN jenis = 'refund' AND status = 'selesai'
                           THEN nilai_barang END), 0)            AS refund_dibayar
    FROM v_laporan_retur
   WHERE tanggal_pengajuan BETWEEN p_awal AND p_akhir
     AND status <> 'ditolak';
END$$

DELIMITER ;
