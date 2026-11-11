<?php
require __DIR__ . '/inc/toko.php';
$me = require_pelanggan();

$id = (int) get('pesanan', post('pesanan'));
$pj = row('SELECT * FROM penjualan WHERE id_penjualan = ? AND id_pelanggan = ?', [$id, $me['id']]);
if (!$pj) {
    flash('warning', 'Pesanan tidak ditemukan.');
    redirect('akun.php');
}
$barang = barang_bisa_diretur($id);

if (is_post()) {
    csrf_check();
    try {
        $jenis = post('jenis') === 'refund' ? 'refund' : 'tukar';
        ajukan_retur($id, $jenis, post('alasan'), date('Y-m-d'), baca_form_retur($barang, $jenis));
        flash('success', 'Retur diajukan. Kirim barang ke gudang kami; tim kami akan memeriksanya.');
        redirect('pesanan.php?id=' . $id);
    } catch (Throwable $e) {
        flash('danger', pesan_error($e));
        redirect('retur_ajukan.php?pesanan=' . $id);
    }
}

toko_header('Ajukan Retur');
?>
<a class="small" href="pesanan.php?id=<?= e($id) ?>">&larr; Kembali ke pesanan</a>
<h1 class="h4 my-2">Ajukan Retur · <?= e($pj['no_pesanan']) ?></h1>
<p class="text-muted small">Batas pengajuan: <b><?= e(tgl(batas_retur($id))) ?></b>. Isi jumlah pada kolom kondisi yang sesuai.</p>
<form method="post" class="card card-body">
  <?= csrf_field() ?>
  <?= form_retur_fields($barang) ?>
  <div><button class="btn btn-hibiscus"><i class="bi bi-send"></i> Kirim Pengajuan</button></div>
</form>
<?php toko_footer();
