<?php
/**
 * Menampilkan bukti bayar untuk admin.
 * URL: admin/bukti_bayar_admin.php?id=<id_pembayaran>
 */
require __DIR__ . '/../inc/admin.php';
require_admin();

$id = (int) get('id');

$bayar = row(
    'SELECT id_pembayaran, bukti_bayar, tipe_bukti FROM pembayaran WHERE id_pembayaran = ?',
    [$id]
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

