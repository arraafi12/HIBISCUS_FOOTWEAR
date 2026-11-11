<?php
require __DIR__ . '/../inc/admin.php';
require_admin();

$id = (int) get('id', post('id'));
$pb = row('SELECT pb.*, s.nama_supplier, s.kota, pg.nama_lengkap
             FROM pembelian pb
             JOIN supplier s  ON s.id_supplier = pb.id_supplier
             JOIN pengguna pg ON pg.id_pengguna = pb.id_pengguna
            WHERE pb.id_pembelian = ?', [$id]);
if (!$pb) {
    flash('warning', 'Pembelian tidak ditemukan.');
    redirect('admin/pembelian.php');
}

if (is_post()) {
    csrf_check();
    try {
        db()->beginTransaction();
        if (post('action') === 'hapus_item') {
            q('DELETE FROM detail_pembelian WHERE id_pembelian = ? AND id_bahan = ?', [$id, (int) post('id_bahan')]);
            db()->commit();
            flash('success', 'Item dihapus, stok bahan dikurangi kembali.');
            redirect('admin/pembelian_detail.php?id=' . $id);
        }
        if (post('action') === 'hapus') {
            q('DELETE FROM detail_pembelian WHERE id_pembelian = ?', [$id]);
            q('DELETE FROM pembelian WHERE id_pembelian = ?', [$id]);
            db()->commit();
            flash('success', 'Pembelian ' . $pb['no_faktur'] . ' dihapus.');
            redirect('admin/pembelian.php');
        }
        db()->commit();
    } catch (PDOException $e) {
        db()->rollBack();
        flash('danger', db_error($e), ['hapus_item' => 'Gagal menghapus item pembelian', 'hapus' => 'Gagal menghapus pembelian'][post('action')] ?? null);
    }
    redirect('admin/pembelian_detail.php?id=' . $id);
}

$items = rows('SELECT d.*, b.kode_bahan, b.nama_bahan, b.satuan
                 FROM detail_pembelian d JOIN bahan_baku b ON b.id_bahan = d.id_bahan
                WHERE d.id_pembelian = ? ORDER BY b.nama_bahan', [$id]);

admin_header('Pembelian ' . $pb['no_faktur'], 'pembelian');
page_title('Pembelian ' . $pb['no_faktur'],
    '<button class="btn btn-outline-secondary" onclick="window.print()">Cetak</button>'
    . delete_button('', ['action' => 'hapus', 'id' => $id],
        'Hapus seluruh pembelian ini? Stok bahan akan dikurangi kembali.', 'Hapus'));
?>
<div class="card">
  <div class="card-body">
    <dl class="row mb-4">
      <dt class="col-sm-2">Tanggal</dt><dd class="col-sm-4"><?= e(tgl($pb['tanggal'])) ?></dd>
      <dt class="col-sm-2">Supplier</dt><dd class="col-sm-4"><?= e($pb['nama_supplier']) ?> (<?= e($pb['kota']) ?>)</dd>
      <dt class="col-sm-2">Dicatat oleh</dt><dd class="col-sm-4"><?= e($pb['nama_lengkap']) ?></dd>
      <dt class="col-sm-2">Keterangan</dt><dd class="col-sm-4"><?= e($pb['keterangan'] ?: '-') ?></dd>
    </dl>
    <div class="table-responsive">
      <table class="table table-sm">
        <thead class="table-light"><tr><th>Kode</th><th>Bahan</th><th class="num">Jumlah</th><th>Satuan</th><th class="num">Harga</th><th class="num">Subtotal</th><th class="d-print-none"></th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= e($it['kode_bahan']) ?></td>
            <td><?= e($it['nama_bahan']) ?></td>
            <td class="num"><?= angka($it['jumlah']) ?></td>
            <td><?= e($it['satuan']) ?></td>
            <td class="num"><?= rupiah($it['harga_satuan']) ?></td>
            <td class="num"><?= rupiah($it['subtotal']) ?></td>
            <td class="text-end d-print-none"><?= delete_button('', ['action' => 'hapus_item', 'id_bahan' => $it['id_bahan']], 'Hapus item ini? Stok bahan dikurangi kembali.') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr class="fw-bold"><td colspan="5">Total</td><td class="num"><?= rupiah($pb['total']) ?></td><td class="d-print-none"></td></tr></tfoot>
      </table>
    </div>
  </div>
</div>
<?php admin_footer();
