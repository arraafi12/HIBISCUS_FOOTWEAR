-- =====================================================================
--  HIBISCUS FOOTWEAR
--  File 99 : Uji coba (laporan, aturan bisnis, konsistensi stok)
--  OPSIONAL - tidak wajib di-import. Jalankan SETELAH semua file lain (01-09).
--  File ini TIDAK mengubah data: semua uji dijalankan di dalam
--  transaksi lalu di-ROLLBACK.
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

SELECT '=== 7. LAPORAN RETUR ===' AS laporan;
SELECT * FROM v_rekap_retur_bulanan ORDER BY periode;
CALL sp_laporan_retur('2026-10-01', '2026-10-31');

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
             + COALESCE((SELECT SUM(dr.jumlah) FROM detail_retur dr
                           JOIN retur r ON r.id_retur = dr.id_retur
                          WHERE dr.id_varian = pu.id_varian AND dr.kondisi = 'baik'
                            AND r.status IN ('diterima','selesai')), 0)
             - COALESCE((SELECT SUM(dr.jumlah) FROM detail_retur dr
                           JOIN retur r ON r.id_retur = dr.id_retur
                          WHERE dr.id_varian_pengganti = pu.id_varian
                            AND r.status IN ('diterima','selesai')), 0)
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
  FROM penjualan pj
UNION ALL
SELECT 'retur (refund)',
       SUM(r.total_refund = (SELECT COALESCE(SUM(subtotal),0) FROM detail_retur d
                              WHERE d.id_retur = r.id_retur)),
       COUNT(*)
  FROM retur r WHERE r.jenis = 'refund';

-- ---------------------------------------------------------------------
--  C. UJI ATURAN BISNIS (setiap perintah HARUS ditolak oleh database)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_uji_harus_gagal;
DELIMITER $$
-- p_setup: perintah persiapan (boleh NULL) yang dijalankan sebelum p_sql
CREATE PROCEDURE sp_uji_harus_gagal(IN p_nama VARCHAR(100), IN p_setup TEXT, IN p_sql TEXT)
BEGIN
  DECLARE v_pesan TEXT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    GET DIAGNOSTICS CONDITION 1 v_pesan = MESSAGE_TEXT;
    ROLLBACK;
    SELECT p_nama AS uji, 'LULUS (ditolak)' AS hasil, v_pesan AS pesan_error;
  END;

  START TRANSACTION;
  IF p_setup IS NOT NULL THEN
    SET @uji_setup = p_setup;
    PREPARE st_setup FROM @uji_setup;
    EXECUTE st_setup;
    DEALLOCATE PREPARE st_setup;
  END IF;
  SET @uji_sql = p_sql;
  PREPARE st FROM @uji_sql;
  EXECUTE st;
  DEALLOCATE PREPARE st;
  ROLLBACK;
  SELECT p_nama AS uji, 'GAGAL (seharusnya ditolak)' AS hasil, NULL AS pesan_error;
END$$
DELIMITER ;

SELECT '=== UJI ATURAN BISNIS ===' AS uji;
CALL sp_uji_harus_gagal('01 Pesan melebihi stok produk', NULL,
  'INSERT INTO detail_penjualan (id_penjualan, id_varian, jumlah, harga_satuan) VALUES (8, 16, 1, NULL)');
CALL sp_uji_harus_gagal('02 Pakai bahan melebihi stok', NULL,
  'INSERT INTO pemakaian_bahan_baku (id_produksi, id_bahan, jumlah) VALUES (5, 2, 100)');
CALL sp_uji_harus_gagal('03 Pembayaran tidak lunas', NULL,
  'INSERT INTO pembayaran (id_penjualan, metode, jumlah_bayar) VALUES (8, ''qris'', 1000)');
SELECT total INTO @tagihan6 FROM penjualan WHERE id_penjualan = 6;
CALL sp_uji_harus_gagal('04 Pembayaran dua kali', NULL,
  CONCAT('INSERT INTO pembayaran (id_penjualan, metode, jumlah_bayar) VALUES (6, ''qris'', ', @tagihan6, ')'));
CALL sp_uji_harus_gagal('05 Kirim sebelum dibayar', NULL,
  'INSERT INTO pengiriman (id_penjualan, kurir, no_resi, tanggal_kirim, id_pengguna) VALUES (8, ''JNE'', ''X1'', ''2026-11-11'', 4)');
CALL sp_uji_harus_gagal('06 Lompat status ke selesai', NULL,
  'UPDATE penjualan SET status = ''selesai'' WHERE id_penjualan = 8');
CALL sp_uji_harus_gagal('07 Batalkan pesanan yang sudah dikirim', NULL,
  'UPDATE penjualan SET status = ''dibatalkan'' WHERE id_penjualan = 5');
CALL sp_uji_harus_gagal('08 Tambah item ke pesanan yang sudah dibayar', NULL,
  'INSERT INTO detail_penjualan (id_penjualan, id_varian, jumlah, harga_satuan) VALUES (6, 2, 1, NULL)');
CALL sp_uji_harus_gagal('09 Ubah detail penjualan', NULL,
  'UPDATE detail_penjualan SET jumlah = 5 WHERE id_penjualan = 8');
CALL sp_uji_harus_gagal('10 Hasil produksi ukuran produk lain', NULL,
  'INSERT INTO hasil_produksi (id_produksi, id_varian, jumlah) VALUES (5, 1, 10)');
CALL sp_uji_harus_gagal('11 Selesaikan produksi tanpa hasil', NULL,
  'UPDATE produksi SET status = ''selesai'' WHERE id_produksi = 5');
CALL sp_uji_harus_gagal('12 Buka kembali produksi selesai', NULL,
  'UPDATE produksi SET status = ''proses'' WHERE id_produksi = 1');
CALL sp_uji_harus_gagal('13 Hapus data pembayaran', NULL,
  'DELETE FROM pembayaran WHERE id_penjualan = 1');
CALL sp_uji_harus_gagal('14 Ukuran di luar rentang 30-50', NULL,
  'INSERT INTO produk_ukuran (id_produk, ukuran, sku) VALUES (1, 60, ''SNK-001-60'')');
CALL sp_uji_harus_gagal('15 Hapus produk yang punya transaksi', NULL,
  'DELETE FROM produk WHERE id_produk = 1');
CALL sp_uji_harus_gagal('16 Hapus pembelian yang bahannya sudah terpakai', NULL,
  'DELETE FROM detail_pembelian WHERE id_pembelian = 2 AND id_bahan = 3');

-- Persiapan untuk uji 19-22: retur baru (status diajukan) atas pesanan #3
SET @setup_refund = 'INSERT INTO retur (no_retur, id_penjualan, tanggal_pengajuan, jenis, alasan) VALUES (''RT-UJI'', 3, ''2026-10-14'', ''refund'', ''uji'')';
SET @setup_tukar  = 'INSERT INTO retur (no_retur, id_penjualan, tanggal_pengajuan, jenis, alasan) VALUES (''RT-UJI'', 3, ''2026-10-14'', ''tukar'', ''uji'')';

CALL sp_uji_harus_gagal('17 Retur pesanan yang belum selesai', NULL,
  'INSERT INTO retur (no_retur, id_penjualan, tanggal_pengajuan, jenis, alasan) VALUES (''RT-UJI'', 5, ''2026-11-11'', ''refund'', ''uji'')');
CALL sp_uji_harus_gagal('18 Retur lewat batas 7 hari', NULL,
  'INSERT INTO retur (no_retur, id_penjualan, tanggal_pengajuan, jenis, alasan) VALUES (''RT-UJI'', 1, ''2026-10-10'', ''refund'', ''uji'')');
CALL sp_uji_harus_gagal('19 Retur melebihi jumlah dibeli (kumulatif)', @setup_refund,
  'INSERT INTO detail_retur (id_retur, id_varian, kondisi, jumlah, harga_satuan) VALUES (LAST_INSERT_ID(), 9, ''baik'', 2, NULL)');
CALL sp_uji_harus_gagal('20 Retur ukuran yang tidak dibeli', @setup_refund,
  'INSERT INTO detail_retur (id_retur, id_varian, kondisi, jumlah, harga_satuan) VALUES (LAST_INSERT_ID(), 1, ''baik'', 1, NULL)');
CALL sp_uji_harus_gagal('21 Tukar dengan produk lain', @setup_tukar,
  'INSERT INTO detail_retur (id_retur, id_varian, kondisi, jumlah, harga_satuan, id_varian_pengganti) VALUES (LAST_INSERT_ID(), 5, ''baik'', 1, NULL, 6)');
CALL sp_uji_harus_gagal('22 Retur tukar tanpa ukuran pengganti', @setup_tukar,
  'INSERT INTO detail_retur (id_retur, id_varian, kondisi, jumlah, harga_satuan) VALUES (LAST_INSERT_ID(), 5, ''baik'', 1, NULL)');
CALL sp_uji_harus_gagal('23 Retur ditolak lalu diterima', NULL,
  'UPDATE retur SET status = ''diterima'', id_pengguna = 2 WHERE id_retur = 2');
CALL sp_uji_harus_gagal('24 Hapus detail retur yang sudah diproses', NULL,
  'DELETE FROM detail_retur WHERE id_retur = 1');

-- ---------------------------------------------------------------------
--  D. SKENARIO ALUR LENGKAP (di-ROLLBACK di akhir)
--  Pesanan #8 (Bloom Canvas 36, menunggu pembayaran) diproses sampai selesai,
--  lalu pelanggan me-refund barangnya, dan produksi #5 (Petal Flat)
--  diselesaikan.
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

-- retur refund 1 hari setelah diterima, kondisi baik -> stok kembali
SELECT 'stok sebelum retur' AS langkah, sku, stok FROM produk_ukuran WHERE id_varian = 6;
INSERT INTO retur (no_retur, id_penjualan, tanggal_pengajuan, jenis, alasan)
VALUES ('RT-UJI-001', 8, '2026-11-14', 'refund', 'Warna tidak sesuai foto');
SET @id_retur = LAST_INSERT_ID();
INSERT INTO detail_retur (id_retur, id_varian, kondisi, jumlah, harga_satuan)
VALUES (@id_retur, 6, 'baik', 1, NULL);
UPDATE retur SET status = 'diterima', id_pengguna = 2 WHERE id_retur = @id_retur;
SELECT 'stok setelah retur diterima' AS langkah, sku, stok FROM produk_ukuran WHERE id_varian = 6;
UPDATE retur SET status = 'selesai', tanggal_selesai = '2026-11-16' WHERE id_retur = @id_retur;
SELECT no_retur, status, total_refund, tanggal_selesai FROM retur WHERE id_retur = @id_retur;
SELECT * FROM v_rekap_penjualan_bulanan WHERE periode = '2026-11';

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
