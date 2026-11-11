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

toko_header($p['nama_produk']);
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
  <li class="breadcrumb-item"><a href="index.php">Katalog</a></li>
  <li class="breadcrumb-item"><a href="index.php?kategori=<?= e($p['id_kategori']) ?>"><?= e($p['nama_kategori']) ?></a></li>
  <li class="breadcrumb-item active"><?= e($p['nama_produk']) ?></li>
</ol></nav>

<div class="row g-4">
  <div class="col-md-6">
    <div class="product-img lg"><i class="bi <?= ikon_kategori($p['nama_kategori']) ?>"></i></div>
  </div>
  <div class="col-md-6">
    <div class="text-muted"><?= e($p['nama_kategori']) ?> · <?= e($p['kode_produk']) ?></div>
    <h1 class="h3"><?= e($p['nama_produk']) ?></h1>
    <div class="h4 text-hibiscus fw-bold mb-3"><?= rupiah($p['harga_jual']) ?></div>
    <p><?= nl2br(e($p['deskripsi'])) ?></p>

    <?php if ($adaStok): ?>
    <form method="post" action="keranjang.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="tambah">
      <div class="mb-2 fw-semibold">Pilih ukuran</div>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach ($ukuran as $u): ?>
          <div class="size-option">
            <input type="radio" name="id_varian" id="u<?= e($u['id_varian']) ?>" value="<?= e($u['id_varian']) ?>" <?= $u['stok'] <= 0 ? 'disabled' : '' ?> required>
            <label class="btn btn-outline-secondary" for="u<?= e($u['id_varian']) ?>" title="Stok <?= e($u['stok']) ?>"><?= e($u['ukuran']) ?></label>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="small text-muted mb-3">
        Stok: <?php foreach ($ukuran as $i => $u) echo ($i ? ', ' : '') . e($u['ukuran']) . ' (' . e($u['stok']) . ')'; ?>
      </div>
      <div class="d-flex gap-2" style="max-width: 320px">
        <input class="form-control" type="number" name="jumlah" value="1" min="1" style="width: 90px" aria-label="Jumlah">
        <button class="btn btn-hibiscus flex-fill"><i class="bi bi-bag-plus"></i> Tambah ke Keranjang</button>
      </div>
    </form>
    <?php else: ?>
      <div class="alert alert-secondary">Stok semua ukuran sedang habis. Produk ini sedang diproduksi ulang.</div>
    <?php endif; ?>

    <ul class="list-unstyled small text-muted mt-4">
      <li><i class="bi bi-hammer"></i> Diproduksi sendiri oleh HIBISCUS FOOTWEAR</li>
      <li><i class="bi bi-credit-card"></i> Pembayaran sekali, lunas (transfer, e-wallet, QRIS, kartu kredit)</li>
      <li><i class="bi bi-arrow-repeat"></i> Tukar ukuran atau refund maksimal 7 hari setelah diterima</li>
    </ul>
  </div>
</div>
<?php toko_footer();
