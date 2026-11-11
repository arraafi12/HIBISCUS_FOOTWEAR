-- =====================================================================
--  HIBISCUS FOOTWEAR
--  File 02 : Trigger (aturan bisnis & pembaruan stok otomatis)
--
--  Ringkasan aturan:
--   - Pembelian bahan baku          -> stok bahan baku BERTAMBAH
--   - Pemakaian bahan pada produksi -> stok bahan baku BERKURANG
--   - Hasil produksi per ukuran     -> stok produk jadi (per ukuran) BERTAMBAH
--   - Detail penjualan (pesanan)    -> stok produk jadi BERKURANG (dipesan)
--   - Pesanan dibatalkan            -> stok produk jadi DIKEMBALIKAN
--   - Pembayaran (harus lunas)      -> status pesanan 'dibayar'
--   - Pengiriman dicatat            -> status 'dikirim'; diterima -> 'selesai'
--   - Detail transaksi tidak boleh di-UPDATE: hapus lalu input ulang,
--     supaya stok selalu konsisten.
-- =====================================================================

USE hibiscus_footwear;

DELIMITER $$

-- ---------------------------------------------------------------------
--  1. DETAIL PEMBELIAN  (stok bahan baku masuk)
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_detail_pembelian_ai
AFTER INSERT ON detail_pembelian FOR EACH ROW
BEGIN
  UPDATE bahan_baku
     SET stok = stok + NEW.jumlah,
         harga_terakhir = NEW.harga_satuan
   WHERE id_bahan = NEW.id_bahan;

  UPDATE pembelian
     SET total = total + (NEW.jumlah * NEW.harga_satuan)
   WHERE id_pembelian = NEW.id_pembelian;
END$$

CREATE TRIGGER trg_detail_pembelian_bu
BEFORE UPDATE ON detail_pembelian FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Detail pembelian tidak boleh diubah. Hapus baris lalu input ulang.';
END$$

CREATE TRIGGER trg_detail_pembelian_bd
BEFORE DELETE ON detail_pembelian FOR EACH ROW
BEGIN
  DECLARE v_stok DECIMAL(12,2);
  DECLARE v_msg  VARCHAR(255);
  SELECT stok INTO v_stok FROM bahan_baku WHERE id_bahan = OLD.id_bahan;
  IF v_stok < OLD.jumlah THEN
    SET v_msg = CONCAT('Detail pembelian tidak bisa dihapus: bahan sudah terpakai (stok sekarang ',
                       v_stok, ', akan dikurangi ', OLD.jumlah, ').');
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
  END IF;
END$$

CREATE TRIGGER trg_detail_pembelian_ad
AFTER DELETE ON detail_pembelian FOR EACH ROW
BEGIN
  UPDATE bahan_baku SET stok = stok - OLD.jumlah WHERE id_bahan = OLD.id_bahan;
  UPDATE pembelian
     SET total = total - (OLD.jumlah * OLD.harga_satuan)
   WHERE id_pembelian = OLD.id_pembelian;
END$$

-- ---------------------------------------------------------------------
--  2. PEMAKAIAN BAHAN BAKU  (stok bahan baku keluar)
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_pemakaian_bi
BEFORE INSERT ON pemakaian_bahan_baku FOR EACH ROW
BEGIN
  DECLARE v_status VARCHAR(20);
  DECLARE v_stok   DECIMAL(12,2);
  DECLARE v_nama   VARCHAR(100);
  DECLARE v_satuan VARCHAR(15);
  DECLARE v_msg    VARCHAR(255);

  SELECT status INTO v_status FROM produksi WHERE id_produksi = NEW.id_produksi;
  IF v_status <> 'proses' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Pemakaian bahan hanya bisa dicatat pada produksi berstatus proses.';
  END IF;

  SELECT stok, nama_bahan, satuan INTO v_stok, v_nama, v_satuan
    FROM bahan_baku WHERE id_bahan = NEW.id_bahan;
  IF v_stok < NEW.jumlah THEN
    SET v_msg = CONCAT('Stok bahan "', v_nama, '" tidak cukup: tersedia ', v_stok, ' ',
                       v_satuan, ', dibutuhkan ', NEW.jumlah, ' ', v_satuan, '.');
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
  END IF;
END$$

CREATE TRIGGER trg_pemakaian_ai
AFTER INSERT ON pemakaian_bahan_baku FOR EACH ROW
BEGIN
  UPDATE bahan_baku SET stok = stok - NEW.jumlah WHERE id_bahan = NEW.id_bahan;
END$$

CREATE TRIGGER trg_pemakaian_bu
BEFORE UPDATE ON pemakaian_bahan_baku FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Pemakaian bahan tidak boleh diubah. Hapus baris lalu input ulang.';
END$$

CREATE TRIGGER trg_pemakaian_bd
BEFORE DELETE ON pemakaian_bahan_baku FOR EACH ROW
BEGIN
  IF (SELECT status FROM produksi WHERE id_produksi = OLD.id_produksi) <> 'proses' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Pemakaian bahan pada produksi yang sudah selesai tidak bisa dihapus.';
  END IF;
END$$

CREATE TRIGGER trg_pemakaian_ad
AFTER DELETE ON pemakaian_bahan_baku FOR EACH ROW
BEGIN
  UPDATE bahan_baku SET stok = stok + OLD.jumlah WHERE id_bahan = OLD.id_bahan;
END$$

-- ---------------------------------------------------------------------
--  3. HASIL PRODUKSI  (stok produk jadi per ukuran masuk)
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_hasil_bi
BEFORE INSERT ON hasil_produksi FOR EACH ROW
BEGIN
  DECLARE v_status         VARCHAR(20);
  DECLARE v_produk_prod    INT UNSIGNED;
  DECLARE v_produk_varian  INT UNSIGNED;

  SELECT status, id_produk INTO v_status, v_produk_prod
    FROM produksi WHERE id_produksi = NEW.id_produksi;
  IF v_status <> 'proses' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Hasil produksi hanya bisa dicatat pada produksi berstatus proses.';
  END IF;

  SELECT id_produk INTO v_produk_varian FROM produk_ukuran WHERE id_varian = NEW.id_varian;
  IF v_produk_varian <> v_produk_prod THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Ukuran/varian yang dicatat bukan milik produk yang sedang diproduksi.';
  END IF;
END$$

CREATE TRIGGER trg_hasil_ai
AFTER INSERT ON hasil_produksi FOR EACH ROW
BEGIN
  UPDATE produk_ukuran SET stok = stok + NEW.jumlah WHERE id_varian = NEW.id_varian;
END$$

CREATE TRIGGER trg_hasil_bu
BEFORE UPDATE ON hasil_produksi FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Hasil produksi tidak boleh diubah. Hapus baris lalu input ulang.';
END$$

CREATE TRIGGER trg_hasil_bd
BEFORE DELETE ON hasil_produksi FOR EACH ROW
BEGIN
  IF (SELECT status FROM produksi WHERE id_produksi = OLD.id_produksi) <> 'proses' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Hasil produksi yang sudah selesai tidak bisa dihapus.';
  END IF;
  IF (SELECT stok FROM produk_ukuran WHERE id_varian = OLD.id_varian) < OLD.jumlah THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Hasil produksi tidak bisa dihapus: sebagian stok sudah terjual.';
  END IF;
END$$

CREATE TRIGGER trg_hasil_ad
AFTER DELETE ON hasil_produksi FOR EACH ROW
BEGIN
  UPDATE produk_ukuran SET stok = stok - OLD.jumlah WHERE id_varian = OLD.id_varian;
END$$

-- ---------------------------------------------------------------------
--  4. PRODUKSI  (validasi perubahan status)
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_produksi_bu
BEFORE UPDATE ON produksi FOR EACH ROW
BEGIN
  IF OLD.status = 'selesai' AND NEW.status <> 'selesai' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Produksi yang sudah selesai tidak bisa dibuka kembali.';
  END IF;

  IF NEW.id_produk <> OLD.id_produk
     AND EXISTS (SELECT 1 FROM hasil_produksi WHERE id_produksi = OLD.id_produksi) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Produk tidak bisa diganti karena hasil produksi sudah dicatat.';
  END IF;

  IF OLD.status = 'proses' AND NEW.status = 'selesai' THEN
    IF NOT EXISTS (SELECT 1 FROM pemakaian_bahan_baku WHERE id_produksi = OLD.id_produksi) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Produksi belum bisa diselesaikan: pemakaian bahan baku belum dicatat.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM hasil_produksi WHERE id_produksi = OLD.id_produksi) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Produksi belum bisa diselesaikan: hasil produksi per ukuran belum dicatat.';
    END IF;
    IF NEW.tanggal_selesai IS NULL THEN
      SET NEW.tanggal_selesai = CURDATE();
    END IF;
  END IF;
END$$

-- ---------------------------------------------------------------------
--  5. DETAIL PENJUALAN  (stok produk jadi keluar + hitung subtotal)
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_detail_penjualan_bi
BEFORE INSERT ON detail_penjualan FOR EACH ROW
BEGIN
  DECLARE v_status VARCHAR(30);
  DECLARE v_stok   INT;
  DECLARE v_sku    VARCHAR(30);
  DECLARE v_harga  DECIMAL(12,2);
  DECLARE v_aktif  TINYINT;
  DECLARE v_msg    VARCHAR(255);

  SELECT status INTO v_status FROM penjualan WHERE id_penjualan = NEW.id_penjualan;
  IF v_status <> 'menunggu_pembayaran' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Item hanya bisa ditambahkan pada pesanan yang belum dibayar.';
  END IF;

  SELECT pu.stok, pu.sku, p.harga_jual, p.aktif
    INTO v_stok, v_sku, v_harga, v_aktif
    FROM produk_ukuran pu JOIN produk p ON p.id_produk = pu.id_produk
   WHERE pu.id_varian = NEW.id_varian;

  IF v_aktif = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Produk sudah tidak aktif dijual.';
  END IF;

  IF v_stok < NEW.jumlah THEN
    SET v_msg = CONCAT('Stok ', v_sku, ' tidak cukup: tersedia ', v_stok,
                       ' pasang, dipesan ', NEW.jumlah, ' pasang.');
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
  END IF;

  -- harga dikunci (snapshot) saat transaksi; jika tidak diisi ambil harga produk
  IF NEW.harga_satuan IS NULL THEN
    SET NEW.harga_satuan = v_harga;
  END IF;
END$$

CREATE TRIGGER trg_detail_penjualan_ai
AFTER INSERT ON detail_penjualan FOR EACH ROW
BEGIN
  UPDATE produk_ukuran SET stok = stok - NEW.jumlah WHERE id_varian = NEW.id_varian;
  UPDATE penjualan
     SET subtotal = subtotal + (NEW.jumlah * NEW.harga_satuan)
   WHERE id_penjualan = NEW.id_penjualan;
END$$

CREATE TRIGGER trg_detail_penjualan_bu
BEFORE UPDATE ON detail_penjualan FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Detail penjualan tidak boleh diubah. Hapus baris lalu input ulang.';
END$$

CREATE TRIGGER trg_detail_penjualan_bd
BEFORE DELETE ON detail_penjualan FOR EACH ROW
BEGIN
  IF (SELECT status FROM penjualan WHERE id_penjualan = OLD.id_penjualan)
       <> 'menunggu_pembayaran' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Item hanya bisa dihapus dari pesanan yang belum dibayar.';
  END IF;
END$$

CREATE TRIGGER trg_detail_penjualan_ad
AFTER DELETE ON detail_penjualan FOR EACH ROW
BEGIN
  UPDATE produk_ukuran SET stok = stok + OLD.jumlah WHERE id_varian = OLD.id_varian;
  UPDATE penjualan
     SET subtotal = subtotal - (OLD.jumlah * OLD.harga_satuan)
   WHERE id_penjualan = OLD.id_penjualan;
END$$

-- ---------------------------------------------------------------------
--  6. PENJUALAN  (alur status pesanan + pengembalian stok saat batal)
--     menunggu_pembayaran -> dibayar | dibatalkan
--     dibayar             -> dikirim | dibatalkan (dana dikembalikan)
--     dikirim             -> selesai
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_penjualan_bu
BEFORE UPDATE ON penjualan FOR EACH ROW
BEGIN
  DECLARE v_msg VARCHAR(255);

  IF NEW.status <> OLD.status THEN
    IF NOT (   (OLD.status = 'menunggu_pembayaran' AND NEW.status IN ('dibayar','dibatalkan'))
            OR (OLD.status = 'dibayar'             AND NEW.status IN ('dikirim','dibatalkan'))
            OR (OLD.status = 'dikirim'             AND NEW.status = 'selesai')) THEN
      SET v_msg = CONCAT('Perubahan status pesanan dari "', OLD.status, '" ke "',
                         NEW.status, '" tidak diizinkan.');
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    IF NEW.status = 'dibayar'
       AND NOT EXISTS (SELECT 1 FROM pembayaran WHERE id_penjualan = OLD.id_penjualan) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Status dibayar diisi otomatis: catat data di tabel pembayaran.';
    END IF;

    IF NEW.status = 'dikirim'
       AND NOT EXISTS (SELECT 1 FROM pengiriman WHERE id_penjualan = OLD.id_penjualan) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Status dikirim diisi otomatis: catat data di tabel pengiriman.';
    END IF;

    IF NEW.status = 'selesai'
       AND NOT EXISTS (SELECT 1 FROM pengiriman
                        WHERE id_penjualan = OLD.id_penjualan AND tanggal_terima IS NOT NULL) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Status selesai diisi otomatis saat tanggal_terima pengiriman diisi.';
    END IF;
  END IF;

  IF OLD.status <> 'menunggu_pembayaran' AND NEW.ongkir <> OLD.ongkir THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Ongkir tidak bisa diubah setelah pesanan dibayar.';
  END IF;
END$$

CREATE TRIGGER trg_penjualan_au
AFTER UPDATE ON penjualan FOR EACH ROW
BEGIN
  IF NEW.status = 'dibatalkan' AND OLD.status <> 'dibatalkan' THEN
    UPDATE produk_ukuran pu
      JOIN detail_penjualan dp ON dp.id_varian = pu.id_varian
       SET pu.stok = pu.stok + dp.jumlah
     WHERE dp.id_penjualan = NEW.id_penjualan;
  END IF;
END$$

-- ---------------------------------------------------------------------
--  7. PEMBAYARAN  (harus satu kali dan LUNAS)
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_pembayaran_bi
BEFORE INSERT ON pembayaran FOR EACH ROW
BEGIN
  DECLARE v_status VARCHAR(30);
  DECLARE v_total  DECIMAL(14,2);
  DECLARE v_msg    VARCHAR(255);

  SELECT status, total INTO v_status, v_total
    FROM penjualan WHERE id_penjualan = NEW.id_penjualan;

  IF v_status <> 'menunggu_pembayaran' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Pesanan ini tidak sedang menunggu pembayaran.';
  END IF;
  IF v_total <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Pesanan belum memiliki item.';
  END IF;
  IF NEW.jumlah_bayar <> v_total THEN
    SET v_msg = CONCAT('Pembayaran harus lunas: total tagihan ', v_total,
                       ', dibayar ', NEW.jumlah_bayar, '.');
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
  END IF;
END$$

CREATE TRIGGER trg_pembayaran_ai
AFTER INSERT ON pembayaran FOR EACH ROW
BEGIN
  UPDATE penjualan SET status = 'dibayar' WHERE id_penjualan = NEW.id_penjualan;
END$$

CREATE TRIGGER trg_pembayaran_bu
BEFORE UPDATE ON pembayaran FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Data pembayaran yang sudah tercatat tidak boleh diubah.';
END$$

CREATE TRIGGER trg_pembayaran_bd
BEFORE DELETE ON pembayaran FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Data pembayaran yang sudah tercatat tidak boleh dihapus.';
END$$

-- ---------------------------------------------------------------------
--  8. PENGIRIMAN
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_pengiriman_bi
BEFORE INSERT ON pengiriman FOR EACH ROW
BEGIN
  IF (SELECT status FROM penjualan WHERE id_penjualan = NEW.id_penjualan) <> 'dibayar' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Pesanan hanya bisa dikirim jika sudah dibayar lunas.';
  END IF;
END$$

CREATE TRIGGER trg_pengiriman_ai
AFTER INSERT ON pengiriman FOR EACH ROW
BEGIN
  UPDATE penjualan SET status = 'dikirim' WHERE id_penjualan = NEW.id_penjualan;
END$$

CREATE TRIGGER trg_pengiriman_bu
BEFORE UPDATE ON pengiriman FOR EACH ROW
BEGIN
  IF NEW.id_penjualan <> OLD.id_penjualan THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Pesanan pada data pengiriman tidak boleh diganti.';
  END IF;
  IF OLD.tanggal_terima IS NOT NULL AND (NEW.tanggal_terima IS NULL OR NEW.tanggal_terima <> OLD.tanggal_terima) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tanggal terima yang sudah dicatat tidak boleh diubah.';
  END IF;
END$$

CREATE TRIGGER trg_pengiriman_au
AFTER UPDATE ON pengiriman FOR EACH ROW
BEGIN
  IF NEW.tanggal_terima IS NOT NULL AND OLD.tanggal_terima IS NULL THEN
    UPDATE penjualan SET status = 'selesai' WHERE id_penjualan = NEW.id_penjualan;
  END IF;
END$$

CREATE TRIGGER trg_pengiriman_bd
BEFORE DELETE ON pengiriman FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Data pengiriman tidak boleh dihapus.';
END$$

DELIMITER ;
