-- =====================================================================
--  HIBISCUS FOOTWEAR
--  File 05 : Uji coba (laporan, aturan bisnis, konsistensi stok)
--  Jalankan SETELAH file 01-04. File ini TIDAK mengubah data:
--  semua uji dijalankan di dalam transaksi lalu di-ROLLBACK.
-- =====================================================================

USE hibiscus_footwear;

-- ---------------------------------------------------------------------
--  A. MENAMPILKAN LAPORAN
-- ---------------------------------------------------------------------
SELECT '=== 1. LAPORAN PEMBELIAN (rekap bulanan per supplier) ===' AS laporan;
SELECT * FROM v_rekap_pembelian_bulanan ORDER BY periode, nama_supplier;
CALL sp_laporan_pembelian('2026-09-01', '2026-09-30', NULL);

SELECT '=== 2. LAPORAN STOK BAHAN BAKU ===' AS laporan;
SELECT * FROM v_laporan_stok_bahan_baku ORDER BY kode_bahan;

SELECT '=== 3. LAPORAN STOK PRODUK JADI (per ukuran) ===' AS laporan;
SELECT * FROM v_laporan_stok_produk ORDER BY kode_produk, ukuran;

SELECT '=== 4. LAPORAN PRODUKSI ===' AS laporan;
SELECT * FROM v_laporan_produksi ORDER BY tanggal_mulai;
SELECT * FROM v_rekap_produksi_bulanan ORDER BY periode, nama_produk;
CALL sp_laporan_produksi('2026-10-01', '2026-10-31');

SELECT '=== 5. LAPORAN PENJUALAN ===' AS laporan;
SELECT * FROM v_rekap_penjualan_bulanan ORDER BY periode;
CALL sp_laporan_penjualan('2026-10-01', '2026-10-31');

SELECT '=== 6. LAPORAN PRODUK TERLARIS ===' AS laporan;
SELECT * FROM v_produk_terlaris ORDER BY peringkat;
SELECT * FROM v_produk_terlaris_per_ukuran ORDER BY peringkat LIMIT 5;
CALL sp_produk_terlaris('2026-09-01', '2026-11-30', 3);

-- ---------------------------------------------------------------------
--  B. UJI KONSISTENSI STOK
--  Stok yang disimpan harus sama dengan stok hasil hitung ulang dari
--  seluruh transaksi. Kolom `cocok` harus bernilai 1 di semua baris.
-- ---------------------------------------------------------------------
SELECT '=== UJI KONSISTENSI STOK BAHAN BAKU ===' AS uji;
SELECT bb.nama_bahan,
       bb.stok AS stok_tersimpan,
       COALESCE((SELECT SUM(jumlah) FROM detail_pembelian d WHERE d.id_bahan = bb.id_bahan), 0)
     - COALESCE((SELECT SUM(jumlah) FROM pemakaian_bahan_baku p WHERE p.id_bahan = bb.id_bahan), 0)
         AS stok_hitung_ulang,
       bb.stok = COALESCE((SELECT SUM(jumlah) FROM detail_pembelian d WHERE d.id_bahan = bb.id_bahan), 0)
               - COALESCE((SELECT SUM(jumlah) FROM pemakaian_bahan_baku p WHERE p.id_bahan = bb.id_bahan), 0)
         AS cocok
  FROM bahan_baku bb;

SELECT '=== UJI KONSISTENSI STOK PRODUK JADI ===' AS uji;
SELECT x.sku, x.stok_tersimpan, x.stok_hitung_ulang, x.stok_tersimpan = x.stok_hitung_ulang AS cocok
  FROM (SELECT pu.sku,
               pu.stok AS stok_tersimpan,
               COALESCE((SELECT SUM(h.jumlah) FROM hasil_produksi h WHERE h.id_varian = pu.id_varian), 0)
             - COALESCE((SELECT SUM(d.jumlah) FROM detail_penjualan d
                           JOIN penjualan j ON j.id_penjualan = d.id_penjualan
                          WHERE d.id_varian = pu.id_varian AND j.status <> 'dibatalkan'), 0)
                 AS stok_hitung_ulang
          FROM produk_ukuran pu) x
 WHERE x.stok_tersimpan <> 0 OR x.stok_hitung_ulang <> 0;

SELECT '=== UJI KONSISTENSI TOTAL TRANSAKSI ===' AS uji;
SELECT 'pembelian' AS tabel,
       SUM(pb.total = (SELECT COALESCE(SUM(subtotal),0) FROM detail_pembelian d
                        WHERE d.id_pembelian = pb.id_pembelian)) AS baris_cocok,
       COUNT(*) AS jumlah_baris
  FROM pembelian pb
UNION ALL
SELECT 'penjualan',
       SUM(pj.subtotal = (SELECT COALESCE(SUM(subtotal),0) FROM detail_penjualan d
                           WHERE d.id_penjualan = pj.id_penjualan)),
       COUNT(*)
  FROM penjualan pj;

-- ---------------------------------------------------------------------
--  C. UJI ATURAN BISNIS (setiap perintah HARUS ditolak oleh database)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_uji_harus_gagal;
DELIMITER $$
CREATE PROCEDURE sp_uji_harus_gagal(IN p_nama VARCHAR(100), IN p_sql TEXT)
BEGIN
  DECLARE v_pesan TEXT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    GET DIAGNOSTICS CONDITION 1 v_pesan = MESSAGE_TEXT;
    ROLLBACK;
    SELECT p_nama AS uji, 'LULUS (ditolak)' AS hasil, v_pesan AS pesan_error;
  END;

  START TRANSACTION;
  SET @uji_sql = p_sql;
  PREPARE st FROM @uji_sql;
  EXECUTE st;
  DEALLOCATE PREPARE st;
  ROLLBACK;
  SELECT p_nama AS uji, 'GAGAL (seharusnya ditolak)' AS hasil, NULL AS pesan_error;
END$$
DELIMITER ;

SELECT '=== UJI ATURAN BISNIS ===' AS uji;
CALL sp_uji_harus_gagal('01 Pesan melebihi stok produk',
  'INSERT INTO detail_penjualan (id_penjualan, id_varian, jumlah, harga_satuan) VALUES (8, 16, 1, NULL)');
CALL sp_uji_harus_gagal('02 Pakai bahan melebihi stok',
  'INSERT INTO pemakaian_bahan_baku (id_produksi, id_bahan, jumlah) VALUES (5, 2, 100)');
CALL sp_uji_harus_gagal('03 Pembayaran tidak lunas',
  'INSERT INTO pembayaran (id_penjualan, metode, jumlah_bayar) VALUES (8, ''qris'', 1000)');
SELECT total INTO @tagihan6 FROM penjualan WHERE id_penjualan = 6;
CALL sp_uji_harus_gagal('04 Pembayaran dua kali',
  CONCAT('INSERT INTO pembayaran (id_penjualan, metode, jumlah_bayar) VALUES (6, ''qris'', ', @tagihan6, ')'));
CALL sp_uji_harus_gagal('05 Kirim sebelum dibayar',
  'INSERT INTO pengiriman (id_penjualan, kurir, no_resi, tanggal_kirim, id_pengguna) VALUES (8, ''JNE'', ''X1'', ''2026-11-11'', 4)');
CALL sp_uji_harus_gagal('06 Lompat status ke selesai',
  'UPDATE penjualan SET status = ''selesai'' WHERE id_penjualan = 8');
CALL sp_uji_harus_gagal('07 Batalkan pesanan yang sudah dikirim',
  'UPDATE penjualan SET status = ''dibatalkan'' WHERE id_penjualan = 5');
CALL sp_uji_harus_gagal('08 Tambah item ke pesanan yang sudah dibayar',
  'INSERT INTO detail_penjualan (id_penjualan, id_varian, jumlah, harga_satuan) VALUES (6, 2, 1, NULL)');
CALL sp_uji_harus_gagal('09 Ubah detail penjualan',
  'UPDATE detail_penjualan SET jumlah = 5 WHERE id_penjualan = 8');
CALL sp_uji_harus_gagal('10 Hasil produksi ukuran produk lain',
  'INSERT INTO hasil_produksi (id_produksi, id_varian, jumlah) VALUES (5, 1, 10)');
CALL sp_uji_harus_gagal('11 Selesaikan produksi tanpa hasil',
  'UPDATE produksi SET status = ''selesai'' WHERE id_produksi = 5');
CALL sp_uji_harus_gagal('12 Buka kembali produksi selesai',
  'UPDATE produksi SET status = ''proses'' WHERE id_produksi = 1');
CALL sp_uji_harus_gagal('13 Hapus data pembayaran',
  'DELETE FROM pembayaran WHERE id_penjualan = 1');
CALL sp_uji_harus_gagal('14 Ukuran di luar rentang 30-50',
  'INSERT INTO produk_ukuran (id_produk, ukuran, sku) VALUES (1, 60, ''SNK-001-60'')');
CALL sp_uji_harus_gagal('15 Hapus produk yang punya transaksi',
  'DELETE FROM produk WHERE id_produk = 1');
CALL sp_uji_harus_gagal('16 Hapus pembelian yang bahannya sudah terpakai',
  'DELETE FROM detail_pembelian WHERE id_pembelian = 2 AND id_bahan = 3');

-- ---------------------------------------------------------------------
--  D. SKENARIO ALUR LENGKAP (di-ROLLBACK di akhir)
--  Pesanan #8 (Bloom Canvas 36, menunggu pembayaran) diproses sampai selesai,
--  lalu produksi #5 (Petal Flat) diselesaikan.
-- ---------------------------------------------------------------------
SELECT '=== SKENARIO ALUR LENGKAP ===' AS uji;
START TRANSACTION;

SELECT no_pesanan, status, total FROM penjualan WHERE id_penjualan = 8;

SELECT total INTO @tagihan FROM penjualan WHERE id_penjualan = 8;
INSERT INTO pembayaran (id_penjualan, metode, jumlah_bayar, no_referensi)
VALUES (8, 'qris', @tagihan, 'QR-UJI-001');
SELECT 'setelah bayar' AS langkah, status FROM penjualan WHERE id_penjualan = 8;

INSERT INTO pengiriman (id_penjualan, kurir, layanan, no_resi, tanggal_kirim, id_pengguna)
VALUES (8, 'JNE', 'REG', 'JNE-UJI-001', '2026-11-11', 4);
SELECT 'setelah kirim' AS langkah, status FROM penjualan WHERE id_penjualan = 8;

UPDATE pengiriman SET tanggal_terima = '2026-11-13' WHERE id_penjualan = 8;
SELECT 'setelah diterima' AS langkah, status FROM penjualan WHERE id_penjualan = 8;

-- selesaikan produksi Petal Flat: catat hasil per ukuran lalu ubah status
INSERT INTO hasil_produksi (id_produksi, id_varian, jumlah) VALUES
 (5, 16, 5), (5, 17, 7), (5, 18, 8), (5, 19, 6), (5, 20, 4);
UPDATE produksi SET status = 'selesai' WHERE id_produksi = 5;
SELECT no_produksi, status, tanggal_selesai, total_hasil_pasang, rincian_ukuran
  FROM v_laporan_produksi WHERE id_produksi = 5;
SELECT sku, stok, status_stok FROM v_laporan_stok_produk WHERE kode_produk = 'FLT-001';

ROLLBACK;

DROP PROCEDURE IF EXISTS sp_uji_harus_gagal;
SELECT '=== UJI COBA SELESAI (data dikembalikan seperti semula) ===' AS info;
