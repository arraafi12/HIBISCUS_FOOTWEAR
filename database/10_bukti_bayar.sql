-- =====================================================================
--  HIBISCUS FOOTWEAR
--  File 10 : Bukti Pembayaran (BLOB) & Update Metode Pembayaran
--  Jalankan setelah 01-09. Aman dijalankan ulang.
-- =====================================================================

USE hibiscus_footwear;

-- 1. Tambah kolom bukti_bayar dan tipe_bukti jika belum ada
SET @ada_bukti = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'pembayaran'
     AND COLUMN_NAME = 'bukti_bayar'
);

SET @sql_tambah_kolom = IF(@ada_bukti = 0,
  'ALTER TABLE pembayaran
     ADD COLUMN bukti_bayar MEDIUMBLOB NULL AFTER no_referensi,
     ADD COLUMN tipe_bukti VARCHAR(30) NULL AFTER bukti_bayar',
  'SELECT "Kolom bukti_bayar sudah ada" AS status'
);

PREPARE stmt FROM @sql_tambah_kolom;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Hapus opsi 'qris' dari ENUM metode (ganti data lama ke transfer_bank jika ada)
-- Catatan: drop trigger sementara jika data pembayaran sudah ada agar tidak memicu error trigger
DROP TRIGGER IF EXISTS trg_pembayaran_bu;

UPDATE pembayaran SET metode = 'transfer_bank' WHERE metode = 'qris';

ALTER TABLE pembayaran
  MODIFY COLUMN metode ENUM('transfer_bank','e_wallet','kartu_kredit') NOT NULL;

-- 3. Kembalikan trigger pembatasan ubah pembayaran
CREATE TRIGGER trg_pembayaran_bu
BEFORE UPDATE ON pembayaran FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Data pembayaran yang sudah tercatat tidak boleh diubah.';
END;

