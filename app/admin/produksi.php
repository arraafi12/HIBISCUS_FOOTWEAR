<?php
require __DIR__ . '/../inc/admin.php';
$u = require_admin();

if (is_post()) {
    csrf_check();
    try {
        q('INSERT INTO produksi (no_produksi, id_produk, tanggal_mulai, id_pengguna, catatan) VALUES (?, ?, ?, ?, ?)',
          [post('no_produksi'), (int) post('id_produk'), post('tanggal_mulai'), $u['id'], post('catatan') ?: null]);
        $id = (int) db()->lastInsertId();
        flash('success', 'Produksi dibuat. Catat pemakaian bahan baku dan hasil per ukuran.');
        redirect('admin/produksi_detail.php?id=' . $id);
    } catch (PDOException $e) {
        flash('danger', db_error($e), 'Gagal membuat produksi');
        redirect('admin/produksi.php');
    }
}

$status = get('status');
$data = rows('SELECT * FROM v_laporan_produksi' . ($status ? ' WHERE status = ?' : '') . ' ORDER BY tanggal_mulai DESC, no_produksi DESC',
             $status ? [$status] : []);
$produk = options('SELECT id_produk, CONCAT(kode_produk, " - ", nama_produk) FROM produk WHERE aktif = 1 ORDER BY kode_produk');

admin_header('Produksi', 'produksi');
page_title('Produksi', '<button class="btn btn-hibiscus" data-bs-toggle="collapse" data-bs-target="#formBaru">Produksi Baru</button>');
?>
<div class="collapse mb-3" id="formBaru">
  <form method="post" class="card card-body">
    <?= csrf_field() ?>
    <div class="row g-3">
      <div class="col-md-3"><label class="form-label">No. Produksi</label>
        <input class="form-control" name="no_produksi" required value="<?= e(next_number('produksi', 'no_produksi', 'PR-' . date('Y-m') . '-')) ?>"></div>
      <div class="col-md-4"><label class="form-label">Produk</label>
        <select class="form-select" name="id_produk" required><option value="">-- pilih produk --</option>
          <?php foreach ($produk as $v => $t): ?><option value="<?= e($v) ?>"><?= e($t) ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-md-2"><label class="form-label">Tanggal Mulai</label>
        <input class="form-control" type="date" name="tanggal_mulai" required value="<?= date('Y-m-d') ?>"></div>
      <div class="col-md-3"><label class="form-label">Catatan</label><input class="form-control" name="catatan" placeholder="mis. Target 50 pasang"></div>
    </div>
    <div class="mt-3"><button class="btn btn-hibiscus">Buat Produksi</button></div>
  </form>
</div>

<div class="card">
  <div class="card-body">
    <div class="btn-group btn-group-sm mb-3">
      <?php foreach (['' => 'Semua', 'proses' => 'Proses', 'selesai' => 'Selesai'] as $k => $t): ?>
        <a class="btn btn-outline-hibiscus <?= $status === $k ? 'active' : '' ?>" href="?status=<?= e($k) ?>"><?= e($t) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover">
        <thead class="table-light"><tr><th>No. Produksi</th><th>Produk</th><th>Mulai</th><th>Selesai</th><th>Status</th><th class="num">Hasil (pasang)</th><th>Rincian Ukuran</th><th>Penanggung Jawab</th></tr></thead>
        <tbody>
        <?php foreach ($data as $r): ?>
          <tr>
            <td><a href="produksi_detail.php?id=<?= e($r['id_produksi']) ?>"><?= e($r['no_produksi']) ?></a></td>
            <td><?= e($r['nama_produk']) ?></td>
            <td><?= e(tgl($r['tanggal_mulai'])) ?></td>
            <td><?= e(tgl($r['tanggal_selesai'])) ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td class="num"><?= angka($r['total_hasil_pasang']) ?></td>
            <td class="small"><?= e($r['rincian_ukuran'] ?? '-') ?></td>
            <td><?= e($r['penanggung_jawab']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data): ?><tr><td colspan="8" class="text-center text-muted py-4">Belum ada produksi.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer();
