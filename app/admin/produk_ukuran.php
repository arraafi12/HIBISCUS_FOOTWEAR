<?php
require __DIR__ . '/../inc/admin.php';
require_admin();

$id = (int) get('id', post('id_produk'));
$produk = row('SELECT p.*, k.nama_kategori FROM produk p JOIN kategori k ON k.id_kategori = p.id_kategori WHERE p.id_produk = ?', [$id]);
if (!$produk) {
    flash('warning', 'Produk tidak ditemukan.');
    redirect('admin/produk.php');
}

if (is_post()) {
    csrf_check();
    try {
        switch (post('action')) {
            case 'tambah':
                $from = (int) post('ukuran_dari');
                $to   = (int) (post('ukuran_sampai') ?: $from);
                $min  = (int) post('stok_minimum', 3);
                db()->beginTransaction();
                for ($u = $from; $u <= $to; $u++) {
                    q('INSERT INTO produk_ukuran (id_produk, ukuran, sku, stok_minimum) VALUES (?, ?, ?, ?)',
                      [$id, $u, $produk['kode_produk'] . '-' . $u, $min]);
                }
                db()->commit();
                flash('success', 'Ukuran berhasil ditambahkan. Stok akan terisi dari Hasil Produksi.');
                break;
            case 'stok_minimum':
                q('UPDATE produk_ukuran SET stok_minimum = ? WHERE id_varian = ? AND id_produk = ?',
                  [(int) post('stok_minimum'), (int) post('id_varian'), $id]);
                flash('success', 'Stok minimum diperbarui.');
                break;
            case 'hapus':
                q('DELETE FROM produk_ukuran WHERE id_varian = ? AND id_produk = ?', [(int) post('id_varian'), $id]);
                flash('success', 'Ukuran dihapus.');
                break;
        }
    } catch (PDOException $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('danger', db_error($e));
    }
    redirect('admin/produk_ukuran.php?id=' . $id);
}

$varian = rows('SELECT * FROM produk_ukuran WHERE id_produk = ? ORDER BY ukuran', [$id]);

admin_header('Ukuran ' . $produk['nama_produk'], 'produk');
page_title('Ukuran & Stok: ' . $produk['nama_produk'],
    '<a class="btn btn-outline-secondary" href="produk.php">Daftar Produk</a>');
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-body">
        <p class="text-muted small mb-2">
          <?= e($produk['kode_produk']) ?> · <?= e($produk['nama_kategori']) ?> · <?= rupiah($produk['harga_jual']) ?>.
          Stok tidak diubah manual: bertambah dari <b>Hasil Produksi</b> dan retur kondisi baik, berkurang dari <b>Penjualan</b>.
        </p>
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead class="table-light"><tr><th>Ukuran</th><th>SKU</th><th class="num">Stok</th><th>Status</th><th>Stok Minimum</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($varian as $v): ?>
              <tr>
                <td class="fw-semibold"><?= e($v['ukuran']) ?></td>
                <td><?= e($v['sku']) ?></td>
                <td class="num"><?= angka($v['stok']) ?></td>
                <td><?= status_badge($v['stok'] == 0 ? 'HABIS' : ($v['stok'] <= $v['stok_minimum'] ? 'MENIPIS' : 'AMAN')) ?></td>
                <td>
                  <form method="post" class="d-flex gap-1" style="max-width: 160px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="stok_minimum">
                    <input type="hidden" name="id_varian" value="<?= e($v['id_varian']) ?>">
                    <input class="form-control form-control-sm" type="number" min="0" name="stok_minimum" value="<?= e($v['stok_minimum']) ?>">
                    <button class="btn btn-sm btn-outline-primary" title="Simpan"><i class="bi bi-check-lg"></i></button>
                  </form>
                </td>
                <td class="text-end"><?= delete_button('', ['action' => 'hapus', 'id_varian' => $v['id_varian']], 'Hapus ukuran ' . $v['ukuran'] . '?') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$varian): ?><tr><td colspan="6" class="text-center text-muted py-3">Belum ada ukuran.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header fw-semibold">Tambah Ukuran</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="tambah">
          <div class="row g-2 mb-2">
            <div class="col"><label class="form-label">Dari</label><input class="form-control" type="number" min="30" max="50" name="ukuran_dari" required></div>
            <div class="col"><label class="form-label">Sampai</label><input class="form-control" type="number" min="30" max="50" name="ukuran_sampai"></div>
          </div>
          <div class="mb-3"><label class="form-label">Stok minimum</label><input class="form-control" type="number" min="0" name="stok_minimum" value="3"></div>
          <button class="btn btn-hibiscus w-100">Tambah</button>
          <div class="form-text">Isi "Sampai" untuk menambah beberapa ukuran sekaligus. SKU dibuat otomatis.</div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php admin_footer();
