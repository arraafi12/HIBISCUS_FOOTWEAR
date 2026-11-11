<?php
require __DIR__ . '/../inc/admin.php';
require_admin();

$idp = (int) get('penjualan', post('id_penjualan'));
$pj  = row('SELECT pj.*, pl.nama_pelanggan FROM penjualan pj JOIN pelanggan pl ON pl.id_pelanggan = pj.id_pelanggan WHERE pj.id_penjualan = ?', [$idp]);
if (!$pj) {
    flash('warning', 'Pesanan tidak ditemukan.');
    redirect('admin/retur.php');
}
$barang = barang_bisa_diretur($idp);

if (is_post()) {
    csrf_check();
    try {
        $jenis = post('jenis') === 'refund' ? 'refund' : 'tukar';
        $id = ajukan_retur($idp, $jenis, post('alasan'), post('tanggal_pengajuan') ?: date('Y-m-d'), baca_form_retur($barang, $jenis));
        flash('success', 'Retur diajukan. Periksa barang lalu terima atau tolak.');
        redirect('admin/retur_detail.php?id=' . $id);
    } catch (Throwable $e) {
        flash('danger', pesan_error($e), 'Gagal mengajukan retur');
        redirect('admin/retur_tambah.php?penjualan=' . $idp);
    }
}

admin_header('Ajukan Retur', 'retur');
page_title('Ajukan Retur: ' . $pj['no_pesanan'],
    '<a class="btn btn-outline-secondary" href="penjualan_detail.php?id=' . e($idp) . '">Kembali</a>');
?>
<form method="post" class="card">
  <div class="card-body">
    <?= csrf_field() ?>
    <p class="mb-3">Pelanggan: <b><?= e($pj['nama_pelanggan']) ?></b> · Status pesanan: <?= status_badge($pj['status']) ?>
       · Batas retur: <b><?= e(tgl(batas_retur($idp))) ?></b></p>
    <div class="mb-3" style="max-width: 220px">
      <label class="form-label">Tanggal pengajuan</label>
      <input class="form-control" type="date" name="tanggal_pengajuan" value="<?= date('Y-m-d') ?>" required>
    </div>
    <?= form_retur_fields($barang) ?>
    <button class="btn btn-hibiscus">Ajukan Retur</button>
  </div>
</form>
<?php admin_footer();
