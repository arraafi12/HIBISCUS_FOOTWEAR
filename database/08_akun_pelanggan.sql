-- =====================================================================
--  HIBISCUS FOOTWEAR
--  File 08 : Akun login pelanggan untuk toko online
--  Jalankan setelah 01-07. Aman dijalankan ulang.
-- =====================================================================

USE hibiscus_footwear;

-- Tambah kolom password_hash pada tabel pelanggan (jika belum ada)
DROP PROCEDURE IF EXISTS sp_tmp_tambah_kolom_password;
DELIMITER $$
CREATE PROCEDURE sp_tmp_tambah_kolom_password()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                  WHERE table_schema = DATABASE()
                    AND table_name   = 'pelanggan'
                    AND column_name  = 'password_hash') THEN
    ALTER TABLE pelanggan ADD COLUMN password_hash VARCHAR(255) NULL AFTER email;
  END IF;
END$$
DELIMITER ;
CALL sp_tmp_tambah_kolom_password();
DROP PROCEDURE sp_tmp_tambah_kolom_password;

-- Password DEMO untuk 5 pelanggan contoh: 'pelanggan123' (hash bcrypt).
-- Pelanggan baru mendaftar sendiri lewat halaman Daftar di toko online.
UPDATE pelanggan
   SET password_hash = '$2y$10$xIffGrNzahAMd2ESc8QvgeWPhRy4ByEHWU/dK/905/x1OLpvAnzCK'
 WHERE id_pelanggan BETWEEN 1 AND 5
   AND password_hash IS NULL;
