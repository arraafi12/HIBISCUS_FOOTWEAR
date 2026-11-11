<?php
require __DIR__ . '/../inc/admin.php';
require_admin();

$id = (int) get('id', post('id'));
$pr = row('SELECT pr.*, p.kode_produk, p.nama_produk, pg.nama_lengkap
             FROM produksi pr
             JOIN produk p    ON p.id_produk = pr.id_produk
             JOIN pengguna pg ON pg.id_pengguna = pr.id_pengguna
            WHERE pr.id_produksi = ?', [$id]);
if (!$pr) {
    flash('warning', 'Produksi tidak ditemukan.');
    redirect('admin/produksi.php');
}

if (is_post()) {
    csrf_check();
    try {
        switch (post('action')) {
            case 'tambah_bahan':
                q('INSERT INTO pemakaian_bahan_baku (id_produksi, id_bahan, jumlah) VALUES (?, ?, ?)',
                  [$id, (int) post('id_bahan'), (float) post('jumlah')]);
                flash('success', 'Pemakaian bahan dicatat, stok bahan berkurang.');
                break;
            case 'hapus_bahan':
                q('DELETE FROM pemakaian_bahan_baku WHERE id_produksi = ? AND id_bahan = ?', [$id, (int) post('id_bahan')]);
                flash('success', 'Pemakaian bahan dihapus, stok bahan dikembalikan.');
                break;
            case 'tambah_hasil':
                q('INSERT INTO hasil_produksi (id_produksi, id_varian, jumlah) VALUES (?, ?, ?)',
                  [$id, (int) post('id_varian'), (int) post('jumlah')]);
                flash('success', 'Hasil produksi dicatat, stok produk bertambah.');
                break;
            case 'hapus_hasil':
                q('DELETE FROM hasil_produksi WHERE id_produksi = ? AND id_varian = ?', [$id, (int) post('id_varian')]);
                flash('success', 'Hasil produksi dihapus, stok produk dikurangi kembali.');
                break;
            case 'selesai':
                q("UPDATE produksi SET status = 'selesai', tanggal_selesai = ? WHERE id_produksi = ?",
                  [post('tanggal_selesai') ?: null, $id]);
                flash('success', 'Produksi ditandai selesai.');
                break;
        }
    } catch (PDOException $e) {
        flash('danger', db_error($e), ['tambah_bahan' => 'Gagal mencatat pemakaian bahan', 'hapus_bahan' => 'Gagal menghapus pemakaian bahan', 'tambah_hasil' => 'Gagal mencatat hasil produksi', 'hapus_hasil' => 'Gagal menghapus hasil produksi', 'selesai' => 'Gagal menyelesaikan produksi'][post('action')] ?? null);
    }
    redirect('admin/produksi_detail.php?id=' . $id);
}

$bahanDipakai = rows('SELECT pk.*, b.nama_bahan, b.satuan, b.stok
                        FROM pemakaian_bahan_baku pk JOIN bahan_baku b ON b.id_bahan = pk.id_bahan
                       WHERE pk.id_produksi = ? ORDER BY b.nama_bahan', [$id]);
$hasil = rows('SELECT h.*, u.ukuran, u.sku FROM hasil_produksi h JOIN produk_ukuran u ON u.id_varian = h.id_varian
                WHERE h.id_produksi = ? ORDER BY u.ukuran', [$id]);
$semuaBahan = rows('SELECT id_bahan, nama_bahan, satuan, stok FROM bahan_baku ORDER BY nama_bahan');
$ukuran     = rows('SELECT id_varian, ukuran, sku, stok FROM produk_ukuran WHERE id_produk = ? ORDER BY ukuran', [$pr['id_produk']]);
$proses     = $pr['status'] === 'proses';

admin_header('Produksi ' . $pr['no_produksi'], 'produksi');
page_title('Produksi ' . $pr['no_produksi'], '<a class="btn btn-outline-secondary" href="produksi.php">Kembali</a>');
?>
<div class="card mb-3">
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-2">Produk</dt><dd class="col-sm-4"><?= e($pr['kode_produk'] . ' - ' . $pr['nama_produk']) ?></dd>
      <dt class="col-sm-2">Status</dt><dd class="col-sm-4"><?= status_badge($pr['status']) ?></dd>
      <dt class="col-sm-2">Mulai</dt><dd class="col-sm-4"><?= e(tgl($pr['tanggal_mulai'])) ?></dd>
      <dt class="col-sm-2">Selesai</dt><dd class="col-sm-4"><?= e(tgl($pr['tanggal_selesai'])) ?></dd>
      <dt class="col-sm-2">Penanggung jawab</dt><dd class="col-sm-4"><?= e($pr['nama_lengkap']) ?></dd>
      <dt class="col-sm-2">Catatan</dt><dd class="col-sm-4"><?= e($pr['catatan'] ?: '-') ?></dd>
    </dl>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header fw-semibold">1. Pemakaian Bahan Baku</div>
      <div class="card-body">
        <table class="table table-sm">
          <thead class="table-light"><tr><th>Bahan</th><th class="num">Jumlah</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($bahanDipakai as $b): ?>
            <tr><td><?= e($b['nama_bahan']) ?></td><td class="num"><?= angka($b['jumlah']) ?> <?= e($b['satuan']) ?></td>
              <td class="text-end"><?= $proses ? delete_button('', ['action' => 'hapus_bahan', 'id_bahan' => $b['id_bahan']], 'Hapus? Stok bahan dikembalikan.') : '' ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$bahanDipakai): ?><tr><td colspan="3" class="text-muted text-center">Belum ada.</td></tr><?php endif; ?>
          </tbody>
        </table>
        <?php if ($proses): ?>
        <form method="post" class="row g-2">
          <?= csrf_field() ?><input type="hidden" name="action" value="tambah_bahan">
          <div class="col-sm-7"><select class="form-select form-select-sm" name="id_bahan" required>
            <option value="">-- pilih bahan --</option>
            <?php foreach ($semuaBahan as $b): ?>
              <option value="<?= e($b['id_bahan']) ?>"><?= e($b['nama_bahan']) ?> (stok <?= angka($b['stok']) ?> <?= e($b['satuan']) ?>)</option>
            <?php endforeach; ?>
          </select></div>
          <div class="col-sm-3"><input class="form-control form-control-sm" type="number" step="0.01" min="0.01" name="jumlah" placeholder="Jumlah" required></div>
          <div class="col-sm-2"><button class="btn btn-sm btn-hibiscus w-100">Tambah</button></div>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header fw-semibold">2. Hasil Produksi per Ukuran</div>
      <div class="card-body">
        <table class="table table-sm">
          <thead class="table-light"><tr><th>Ukuran</th><th>SKU</th><th class="num">Jumlah (pasang)</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($hasil as $h): ?>
            <tr><td><?= e($h['ukuran']) ?></td><td><?= e($h['sku']) ?></td><td class="num"><?= angka($h['jumlah']) ?></td>
              <td class="text-end"><?= $proses ? delete_button('', ['action' => 'hapus_hasil', 'id_varian' => $h['id_varian']], 'Hapus? Stok produk dikurangi kembali.') : '' ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$hasil): ?><tr><td colspan="4" class="text-muted text-center">Belum ada.</td></tr><?php endif; ?>
          </tbody>
          <?php if ($hasil): ?><tfoot><tr class="fw-semibold"><td colspan="2">Total</td><td class="num"><?= angka(array_sum(array_column($hasil, 'jumlah'))) ?></td><td></td></tr></tfoot><?php endif; ?>
        </table>
        <?php if ($proses): ?>
          <?php if (!$ukuran): ?>
            <div class="alert alert-warning py-2 small">Produk ini belum punya ukuran. <a href="produk_ukuran.php?id=<?= e($pr['id_produk']) ?>">Tambah ukuran</a> dulu.</div>
          <?php else: ?>
          <form method="post" class="row g-2">
            <?= csrf_field() ?><input type="hidden" name="action" value="tambah_hasil">
            <div class="col-sm-7"><select class="form-select form-select-sm" name="id_varian" required>
              <option value="">-- pilih ukuran --</option>
              <?php foreach ($ukuran as $v): ?><option value="<?= e($v['id_varian']) ?>">Ukuran <?= e($v['ukuran']) ?> (<?= e($v['sku']) ?>)</option><?php endforeach; ?>
            </select></div>
            <div class="col-sm-3"><input class="form-control form-control-sm" type="number" min="1" name="jumlah" placeholder="Pasang" required></div>
            <div class="col-sm-2"><button class="btn btn-sm btn-hibiscus w-100">Tambah</button></div>
          </form>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($proses): ?>
<div class="card mt-3">
  <div class="card-body">
    <form method="post" class="row g-2 align-items-end" data-confirm="Tandai produksi selesai? Data pemakaian dan hasil tidak bisa diubah lagi.">
      <?= csrf_field() ?><input type="hidden" name="action" value="selesai">
      <div class="col-auto"><label class="form-label">3. Tanggal selesai</label>
        <input class="form-control" type="date" name="tanggal_selesai" value="<?= date('Y-m-d') ?>" min="<?= e($pr['tanggal_mulai']) ?>"></div>
      <div class="col-auto"><button class="btn btn-success">Tandai Selesai</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
<?php admin_footer();
