<?php
/**
 * Menampilkan bukti bayar (gambar/PDF) yang tersimpan di DB.
 * URL: bukti_bayar.php?id=<id_pembayaran>
 */
require __DIR__ . '/inc/toko.php';
$me = require_pelanggan();

$id = (int) get('id');

// Pastikan bukti milik pesanan milik pelanggan ini
$bayar = row(
    'SELECT pb.id_pembayaran, pb.bukti_bayar, pb.tipe_bukti
       FROM pembayaran pb
       JOIN penjualan pj ON pj.id_penjualan = pb.id_penjualan
      WHERE pb.id_pembayaran = ? AND pj.id_pelanggan = ?',
    [$id, $me['id']]
);

if (!$bayar || !$bayar['bukti_bayar']) {
    http_response_code(404);
    exit('Bukti bayar tidak ditemukan.');
}

$mime = $bayar['tipe_bukti'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="bukti-bayar-' . $id . '"');
header('Cache-Control: private, max-age=3600');
echo $bayar['bukti_bayar'];

