-- =====================================================================
--  HIBISCUS FOOTWEAR
--  File 07 : Data contoh retur (jalankan setelah 04 dan 06)
--  Cukup dijalankan SATU kali. Jika dijalankan ulang akan berhenti di
--  error 'Duplicate entry' dan tidak mengubah apa pun.
-- =====================================================================

USE hibiscus_footwear;

-- RT-001: pesanan ORD-20261009-001 (diterima 2026-10-11)
-- Bloom Canvas 39 kekecilan -> TUKAR ke ukuran 40, barang kondisi baik
INSERT INTO retur (id_retur, no_retur, id_penjualan, tanggal_pengajuan, jenis, alasan) VALUES
 (1, 'RT-2026-10-001', 3, '2026-10-13', 'tukar', 'Ukuran kekecilan');
INSERT INTO detail_retur (id_retur, id_varian, kondisi, jumlah, harga_satuan, id_varian_pengganti) VALUES
 (1, 9, 'baik', 1, NULL, 10);
UPDATE retur SET status = 'diterima', id_pengguna = 2 WHERE id_retur = 1;
UPDATE retur SET status = 'selesai', tanggal_selesai = '2026-10-17',
       catatan = 'Barang pengganti dikirim JNE0000000003' WHERE id_retur = 1;

-- RT-002: pesanan ORD-20260928-001 (diterima 2026-09-30)
-- Pelanggan berubah pikiran setelah lewat pemeriksaan -> DITOLAK
INSERT INTO retur (id_retur, no_retur, id_penjualan, tanggal_pengajuan, jenis, alasan) VALUES
 (2, 'RT-2026-10-002', 2, '2026-10-03', 'refund', 'Berubah pikiran');
INSERT INTO detail_retur (id_retur, id_varian, kondisi, jumlah, harga_satuan, id_varian_pengganti) VALUES
 (2, 13, 'baik', 1, NULL, NULL);
UPDATE retur SET status = 'ditolak', id_pengguna = 2,
       catatan = 'Barang sudah dipakai, tidak memenuhi syarat retur' WHERE id_retur = 2;

-- RT-003: pesanan ORD-20261018-001 (diterima 2026-10-22)
-- Tropica Sandal 38 sol lepas -> REFUND, barang cacat (tidak kembali ke stok)
INSERT INTO retur (id_retur, no_retur, id_penjualan, tanggal_pengajuan, jenis, alasan) VALUES
 (3, 'RT-2026-10-003', 4, '2026-10-24', 'refund', 'Sol lepas');
INSERT INTO detail_retur (id_retur, id_varian, kondisi, jumlah, harga_satuan, id_varian_pengganti) VALUES
 (3, 23, 'cacat', 1, NULL, NULL);
UPDATE retur SET status = 'diterima', id_pengguna = 2 WHERE id_retur = 3;
UPDATE retur SET status = 'selesai', tanggal_selesai = '2026-10-28',
       catatan = 'Refund via transfer bank' WHERE id_retur = 3;

-- RT-004: pesanan ORD-20261018-001 -> Bloom Canvas 37 TUKAR ke 38
-- Barang sudah diterima gudang, pengganti belum dikirim (status: diterima)
INSERT INTO retur (id_retur, no_retur, id_penjualan, tanggal_pengajuan, jenis, alasan) VALUES
 (4, 'RT-2026-10-004', 4, '2026-10-26', 'tukar', 'Ukuran kekecilan');
INSERT INTO detail_retur (id_retur, id_varian, kondisi, jumlah, harga_satuan, id_varian_pengganti) VALUES
 (4, 7, 'baik', 1, NULL, 8);
UPDATE retur SET status = 'diterima', id_pengguna = 2 WHERE id_retur = 4;
