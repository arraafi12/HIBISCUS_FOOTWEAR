<?php
/**
 * Menampilkan foto produk yang disimpan sebagai BLOB di tabel produk_foto.
 * Pemakaian: <img src="foto.php?id=3&v=1731300000">
 *   id = id_produk, v = versi (timestamp unggah) agar cache browser ikut berganti.
 */
require __DIR__ . '/inc/bootstrap.php';
session_write_close();   // gambar tidak perlu mengunci sesi

$id = (int) get('id');
$f = foto_siap() ? row('SELECT foto, tipe_mime, ukuran_byte, UNIX_TIMESTAMP(diunggah_pada) AS versi
                          FROM produk_foto WHERE id_produk = ?', [$id]) : null;
if (!$f) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Foto tidak ditemukan.');
}

$etag = '"foto-' . $id . '-' . $f['versi'] . '"';
header_remove('Pragma');
header_remove('Expires');
header('Cache-Control: public, max-age=604800');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');

if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Type: ' . $f['tipe_mime']);
header('Content-Length: ' . strlen($f['foto']));
echo $f['foto'];
