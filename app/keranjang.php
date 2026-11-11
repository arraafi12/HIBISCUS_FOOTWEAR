<?php
require __DIR__ . '/inc/toko.php';

if (is_post()) {
    csrf_check();
    $idv = (int) post('id_varian');
    $stok = (int) val('SELECT pu.stok FROM produk_ukuran pu JOIN produk p ON p.id_produk = pu.id_produk WHERE pu.id_varian = ? AND p.aktif = 1', [$idv]);
    switch (post('action')) {
        case 'tambah':
            $jml = (keranjang()[$idv] ?? 0) + max(1, (int) post('jumlah', 1));
            if ($stok <= 0) {
                flash('danger', 'Ukuran ini sedang habis.');
            } else {
                if ($jml > $stok) {
                    $jml = $stok;
                    flash('warning', "Jumlah disesuaikan dengan stok yang tersedia ($stok pasang).");
                } else {
                    flash('success', 'Produk ditambahkan ke keranjang.');
                }
                keranjang_set($idv, $jml);
            }
            break;
        case 'ubah':
            $jml = (int) post('jumlah');
            if ($jml > $stok) {
                $jml = $stok;
                flash('warning', "Stok tersedia hanya $stok pasang.");
            }
            keranjang_set($idv, $jml);
            break;
        case 'hapus':
            keranjang_set($idv, 0);
            break;
    }
    redirect('keranjang.php');
}

$isi = keranjang_detail();
$total = array_sum(array_column($isi, 'subtotal'));
$adaMasalah = (bool) array_filter($isi, fn($i) => $i['jumlah'] > $i['stok'] || !$i['aktif']);

toko_header('Keranjang');
?>
<h1 class="h4 mb-3">Keranjang Belanja</h1>
<?php if (!$isi): ?>
  <div class="text-center py-5">
    <i class="bi bi-bag display-4 text-muted"></i>
    <p class="mt-3 text-muted">Keranjang masih kosong.</p>
    <a class="btn btn-hibiscus" href="index.php">Lihat katalog</a>
  </div>
<?php else: ?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card">
      <ul class="list-group list-group-flush">
        <?php foreach ($isi as $i): ?>
          <li class="list-group-item py-3">
            <div class="d-flex gap-3 align-items-center">
              <div class="product-img rounded" style="width:72px;font-size:1.8rem"><i class="bi <?= ikon_kategori($i['nama_kategori']) ?>"></i></div>
              <div class="flex-fill">
                <a class="fw-semibold text-reset" href="produk.php?id=<?= e($i['id_produk']) ?>"><?= e($i['nama_produk']) ?></a>
                <div class="small text-muted">Ukuran <?= e($i['ukuran']) ?> · <?= rupiah($i['harga_jual']) ?> · stok <?= e($i['stok']) ?></div>
                <?php if ($i['jumlah'] > $i['stok']): ?><div class="small text-danger">Stok tidak cukup, kurangi jumlah.</div><?php endif; ?>
              </div>
              <form method="post" class="d-flex gap-1">
                <?= csrf_field() ?><input type="hidden" name="action" value="ubah"><input type="hidden" name="id_varian" value="<?= e($i['id_varian']) ?>">
                <input class="form-control form-control-sm" type="number" min="0" max="<?= e($i['stok']) ?>" name="jumlah" value="<?= e($i['jumlah']) ?>" style="width:70px" aria-label="Jumlah">
                <button class="btn btn-sm btn-outline-secondary" title="Perbarui"><i class="bi bi-arrow-repeat"></i></button>
              </form>
              <div class="fw-semibold text-end" style="min-width:110px"><?= rupiah($i['subtotal']) ?></div>
              <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="hapus"><input type="hidden" name="id_varian" value="<?= e($i['id_varian']) ?>">
                <button class="btn btn-sm btn-link text-danger" title="Hapus"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2"><span>Subtotal (<?= keranjang_jumlah() ?> pasang)</span><b><?= rupiah($total) ?></b></div>
        <div class="small text-muted mb-3">Ongkir dihitung di halaman checkout sesuai kota tujuan.</div>
        <a class="btn btn-hibiscus w-100 <?= $adaMasalah ? 'disabled' : '' ?>" href="checkout.php">Lanjut ke Checkout</a>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php toko_footer();
