<?php
require __DIR__ . '/../inc/admin.php';
$u = require_admin();

$id = (int) get('id', post('id'));
$r = row('SELECT r.*, pj.no_pesanan, pl.nama_pelanggan, pg.nama_lengkap AS pemeriksa
            FROM retur r
            JOIN penjualan pj ON pj.id_penjualan = r.id_penjualan
            JOIN pelanggan pl ON pl.id_pelanggan = pj.id_pelanggan
            LEFT JOIN pengguna pg ON pg.id_pengguna = r.id_pengguna
           WHERE r.id_retur = ?', [$id]);
if (!$r) {
    flash('warning', 'Retur tidak ditemukan.');
    redirect('admin/retur.php');
}

if (is_post()) {
    csrf_check();
    try {
        $catatan = post('catatan') ?: null;
        switch (post('action')) {
            case 'terima':
                q("UPDATE retur SET status = 'diterima', id_pengguna = ?, catatan = COALESCE(?, catatan) WHERE id_retur = ?", [$u['id'], $catatan, $id]);
                flash('success', 'Retur diterima. Stok sudah diperbarui sesuai kondisi barang.');
                break;
            case 'tolak':
                q("UPDATE retur SET status = 'ditolak', id_pengguna = ?, catatan = COALESCE(?, catatan) WHERE id_retur = ?", [$u['id'], $catatan, $id]);
                flash('success', 'Retur ditolak.');
                break;
            case 'selesai':
                q("UPDATE retur SET status = 'selesai', tanggal_selesai = ?, catatan = COALESCE(?, catatan) WHERE id_retur = ?",
                  [post('tanggal_selesai') ?: null, $catatan, $id]);
                flash('success', $r['jenis'] === 'refund' ? 'Refund selesai dan tercatat mengurangi omzet.' : 'Penukaran selesai.');
                break;
        }
    } catch (PDOException $e) {
        flash('danger', db_error($e));
    }
    redirect('admin/retur_detail.php?id=' . $id);
}

$items = rows('SELECT * FROM v_laporan_retur WHERE id_retur = ? ORDER BY nama_produk, ukuran_retur', [$id]);

admin_header('Retur ' . $r['no_retur'], 'retur');
page_title('Retur ' . $r['no_retur'], '<a class="btn btn-outline-secondary" href="retur.php"><i class="bi bi-arrow-left"></i> Kembali</a>');
?>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="card">
      <div class="card-body">
        <dl class="row">
          <dt class="col-sm-3">Pesanan</dt><dd class="col-sm-9"><a href="penjualan_detail.php?id=<?= e($r['id_penjualan']) ?>"><?= e($r['no_pesanan']) ?></a> · <?= e($r['nama_pelanggan']) ?></dd>
          <dt class="col-sm-3">Jenis</dt><dd class="col-sm-9"><?= e(label($r['jenis'])) ?></dd>
          <dt class="col-sm-3">Alasan</dt><dd class="col-sm-9"><?= e($r['alasan']) ?></dd>
          <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><?= status_badge($r['status']) ?></dd>
          <dt class="col-sm-3">Diajukan</dt><dd class="col-sm-9"><?= e(tgl($r['tanggal_pengajuan'])) ?></dd>
          <dt class="col-sm-3">Selesai</dt><dd class="col-sm-9"><?= e(tgl($r['tanggal_selesai'])) ?></dd>
          <dt class="col-sm-3">Pemeriksa</dt><dd class="col-sm-9"><?= e($r['pemeriksa'] ?: '-') ?></dd>
          <dt class="col-sm-3">Catatan</dt><dd class="col-sm-9"><?= e($r['catatan'] ?: '-') ?></dd>
        </dl>
        <table class="table table-sm">
          <thead class="table-light"><tr><th>Produk</th><th>Ukuran</th><th>Kondisi</th><th class="num">Jumlah</th><th class="num">Nilai</th><th>Tukar ke</th></tr></thead>
          <tbody>
          <?php foreach ($items as $it): ?>
            <tr><td><?= e($it['nama_produk']) ?></td><td><?= e($it['ukuran_retur']) ?></td>
                <td><?= $it['kondisi'] === 'cacat' ? '<span class="badge bg-danger">Cacat</span>' : '<span class="badge bg-success">Baik</span>' ?></td>
                <td class="num"><?= angka($it['jumlah']) ?></td><td class="num"><?= rupiah($it['nilai_barang']) ?></td>
                <td><?= e($it['ukuran_pengganti'] ?? '-') ?></td></tr>
          <?php endforeach; ?>
          </tbody>
          <?php if ($r['jenis'] === 'refund'): ?>
          <tfoot><tr class="fw-bold"><td colspan="4" class="text-end">Total refund</td><td class="num"><?= rupiah($r['total_refund']) ?></td><td></td></tr></tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </div>
  <div class="col-xl-4">
    <?php if (in_array($r['status'], ['diajukan', 'diterima'], true)): ?>
    <div class="card">
      <div class="card-header bg-white fw-semibold">Proses Retur</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input class="form-control form-control-sm mb-2" name="catatan" placeholder="Catatan (opsional)">
          <?php if ($r['status'] === 'diajukan'): ?>
            <p class="small text-muted">Periksa barang yang dikirim pelanggan. Saat diterima, barang kondisi baik masuk stok
              <?= $r['jenis'] === 'tukar' ? 'dan ukuran pengganti dikeluarkan dari stok' : '' ?>.</p>
            <div class="d-flex gap-2">
              <button class="btn btn-sm btn-success flex-fill" name="action" value="terima">Terima Barang</button>
              <button class="btn btn-sm btn-outline-danger flex-fill" name="action" value="tolak" onclick="return confirm('Tolak retur ini?')">Tolak</button>
            </div>
          <?php else: ?>
            <p class="small text-muted"><?= $r['jenis'] === 'refund'
                ? 'Tandai selesai setelah dana ' . e(rupiah($r['total_refund'])) . ' ditransfer ke pelanggan.'
                : 'Tandai selesai setelah barang pengganti dikirim ke pelanggan.' ?></p>
            <input class="form-control form-control-sm mb-2" type="date" name="tanggal_selesai" value="<?= date('Y-m-d') ?>" min="<?= e($r['tanggal_pengajuan']) ?>">
            <button class="btn btn-sm btn-success w-100" name="action" value="selesai">Tandai Selesai</button>
          <?php endif; ?>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer();
