<?php
require __DIR__ . '/inc/toko.php';

$id = (int) get('id');
$p = row('SELECT p.*, k.nama_kategori FROM produk p JOIN kategori k ON k.id_kategori = p.id_kategori WHERE p.id_produk = ? AND p.aktif = 1', [$id]);
if (!$p) {
    flash('warning', 'Produk tidak ditemukan.');
    redirect('index.php');
}
$ukuran = rows('SELECT * FROM produk_ukuran WHERE id_produk = ? ORDER BY ukuran', [$id]);
$adaStok = array_filter($ukuran, fn($u) => $u['stok'] > 0);

toko_header($p['nama_produk'], 'katalog');
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
  <li class="breadcrumb-item"><a href="index.php">Katalog</a></li>
  <li class="breadcrumb-item"><a href="index.php?kategori=<?= e($p['id_kategori']) ?>"><?= e($p['nama_kategori']) ?></a></li>
  <li class="breadcrumb-item active" aria-current="page"><?= e($p['nama_produk']) ?></li>
</ol></nav>

<div class="row g-4 g-lg-5">
  <div class="col-md-6">
    <?= produk_tile($p['nama_produk'], $p['kode_produk'], $p['nama_kategori'], 'lg') ?>
  </div>
  <div class="col-md-6 col-lg-5">
    <div class="eyebrow"><?= e($p['nama_kategori']) ?> · <span class="mono"><?= e($p['kode_produk']) ?></span></div>
    <h1 class="h2 mt-1 mb-2"><?= e($p['nama_produk']) ?></h1>
    <div class="fs-5 mb-3"><?= rupiah($p['harga_jual']) ?></div>
    <?php if ($p['deskripsi']): ?><p class="text-muted"><?= nl2br(e($p['deskripsi'])) ?></p><?php endif; ?>

    <?php if ($adaStok): ?>
    <form method="post" action="keranjang.php" class="my-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="tambah">
      <fieldset>
        <legend class="form-label mb-2">Ukuran</legend>
        <div class="size-grid mb-2">
          <?php foreach ($ukuran as $u): ?>
            <div class="size-option">
              <input type="radio" name="id_varian" id="u<?= e($u['id_varian']) ?>" value="<?= e($u['id_varian']) ?>" <?= $u['stok'] <= 0 ? 'disabled' : '' ?> required>
              <label class="btn" for="u<?= e($u['id_varian']) ?>"><?= e($u['ukuran']) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="small text-muted mb-3">
          Stok per ukuran: <span class="mono"><?php foreach ($ukuran as $i => $u) echo ($i ? ' · ' : '') . e($u['ukuran']) . '/' . e($u['stok']); ?></span>
        </div>
      </fieldset>
      <div class="d-flex gap-2">
        <label class="visually-hidden" for="jumlah">Jumlah</label>
        <input class="form-control" type="number" id="jumlah" name="jumlah" value="1" min="1" style="width: 80px">
        <button class="btn btn-hibiscus flex-fill">Tambah ke keranjang</button>
      </div>
    </form>
    <?php else: ?>
      <p class="my-4 py-3 border-top border-bottom">Semua ukuran sedang habis. Model ini masuk jadwal produksi berikutnya.</p>
    <?php endif; ?>

    <dl class="spec">
      <dt>Produksi</dt><dd>Dibuat sendiri oleh tim produksi Hibiscus</dd>
      <dt>Pembayaran</dt><dd>Sekali bayar, lunas: transfer bank, e-wallet, QRIS, atau kartu kredit</dd>
      <dt>Retur</dt><dd>Tukar ukuran atau refund, paling lambat 7 hari setelah barang diterima</dd>
    </dl>
  </div>
</div>
<?php toko_footer();
