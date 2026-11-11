-- =====================================================================
--  HIBISCUS FOOTWEAR
--  File 09 : Foto produk (disimpan sebagai data biner / BLOB)
--  Jalankan setelah 01-08. Aman dijalankan ulang (tidak menghapus foto).
--
--  Satu produk memiliki satu foto utama. Foto disimpan di tabel terpisah
--  agar query katalog (SELECT * FROM produk) tidak ikut membaca data biner
--  yang besar. Aplikasi membaca foto lewat app/foto.php?id=<id_produk>.
--
--  Catatan ukuran: MEDIUMBLOB menampung s.d. 16 MB, tetapi aplikasi
--  membatasi foto maks. 900 KB karena max_allowed_packet bawaan XAMPP 1 MB.
-- =====================================================================

USE hibiscus_footwear;

CREATE TABLE IF NOT EXISTS produk_foto (
  id_produk      INT UNSIGNED       NOT NULL,
  foto           MEDIUMBLOB         NOT NULL,          -- isi file gambar (biner)
  tipe_mime      VARCHAR(30)        NOT NULL,          -- image/jpeg | image/png | image/webp
  ukuran_byte    INT UNSIGNED       NOT NULL,
  lebar          SMALLINT UNSIGNED  NULL,              -- piksel
  tinggi         SMALLINT UNSIGNED  NULL,
  diunggah_pada  TIMESTAMP          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_produk),
  CONSTRAINT fk_foto_produk FOREIGN KEY (id_produk)
    REFERENCES produk (id_produk) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT chk_foto_mime   CHECK (tipe_mime IN ('image/jpeg', 'image/png', 'image/webp')),
  CONSTRAINT chk_foto_ukuran CHECK (ukuran_byte > 0 AND ukuran_byte <= 921600)
) ENGINE=InnoDB;
