<?php
require __DIR__ . '/inc/toko.php';

$kat  = (int) get('kategori');
$cari = get('q');

$where  = ['p.aktif = 1'];
$params = [];
if ($kat) {
    $where[] = 'p.id_kategori = ?';
    $params[] = $kat;
}
if ($cari !== '') {
    $where[] = '(p.nama_produk LIKE ? OR p.deskripsi LIKE ?)';
    array_push($params, "%$cari%", "%$cari%");
}
$produk = rows('SELECT p.*, k.nama_kategori,
                       (SELECT COALESCE(SUM(stok),0) FROM produk_ukuran u WHERE u.id_produk = p.id_produk) AS stok,
                       (SELECT GROUP_CONCAT(ukuran ORDER BY ukuran) FROM produk_ukuran u WHERE u.id_produk = p.id_produk AND u.stok > 0) AS ukuran_tersedia
                  FROM produk p JOIN kategori k ON k.id_kategori = p.id_kategori
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY stok = 0, p.nama_produk', $params);
$kategori = rows('SELECT k.* FROM kategori k WHERE EXISTS (SELECT 1 FROM produk p WHERE p.id_kategori = k.id_kategori AND p.aktif = 1) ORDER BY nama_kategori');
$terlaris = array_column(rows('SELECT kode_produk FROM v_produk_terlaris WHERE peringkat <= 2'), 'kode_produk');
$ukuran   = rows('SELECT MIN(ukuran) AS min_u, MAX(ukuran) AS max_u FROM produk_ukuran')[0];

toko_header('Katalog', 'katalog');
?>
<section class="intro">
  <div class="eyebrow mb-2">Koleksi <?= date('Y') ?></div>
  <h1>Sepatu yang kami potong, jahit, dan rakit sendiri.</h1>
  <p>Setiap pasang dibuat per batch oleh tim produksi Hibiscus. Pilih ukuran yang tersedia; stok di halaman ini adalah stok gudang saat ini.</p>
  <div class="facts">
    <span>Ukuran <?= e($ukuran['min_u']) ?>–<?= e($ukuran['max_u']) ?></span>
    <span>Bayar sekali, lunas</span>
    <span>Tukar ukuran dalam 7 hari</span>
  </div>
</section>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <nav class="filters" aria-label="Kategori">
    <a class="<?= !$kat ? 'active' : '' ?>" href="?">Semua</a>
    <?php foreach ($kategori as $k): ?>
      <a class="<?= $kat === (int) $k['id_kategori'] ? 'active' : '' ?>" href="?kategori=<?= e($k['id_kategori']) ?>"><?= e($k['nama_kategori']) ?></a>
    <?php endforeach; ?>
  </nav>
  <form class="d-flex gap-2" method="get" role="search">
    <?php if ($kat): ?><input type="hidden" name="kategori" value="<?= e($kat) ?>"><?php endif; ?>
    <label class="visually-hidden" for="q">Cari produk</label>
    <input class="form-control form-control-sm" id="q" name="q" value="<?= e($cari) ?>" placeholder="Cari model…" style="width: 200px">
    <button class="btn btn-sm btn-outline-secondary">Cari</button>
  </form>
</div>

<?php if ($cari !== ''): ?>
  <p class="text-muted small mb-3"><?= count($produk) ?> hasil untuk "<?= e($cari) ?>" · <a href="?<?= $kat ? 'kategori=' . e($kat) : '' ?>">hapus pencarian</a></p>
<?php endif; ?>

<div class="row g-4 g-lg-5">
  <?php foreach ($produk as $p): ?>
    <div class="col-6 col-md-4 col-lg-3">
      <a class="product" href="produk.php?id=<?= e($p['id_produk']) ?>">
        <?= produk_tile($p['nama_produk'], $p['kode_produk'], $p['nama_kategori'], '',
              $p['stok'] == 0 ? 'Habis' : (in_array($p['kode_produk'], $terlaris, true) ? 'Terlaris' : '')) ?>
        <div class="product-name"><?= e($p['nama_produk']) ?></div>
        <div class="product-price"><?= rupiah($p['harga_jual']) ?></div>
        <div class="product-sizes"><?= $p['ukuran_tersedia'] ? e(str_replace(',', ' · ', $p['ukuran_tersedia'])) : 'Sedang diproduksi ulang' ?></div>
      </a>
    </div>
  <?php endforeach; ?>
  <?php if (!$produk): ?>
    <div class="col-12 py-5 text-muted">Tidak ada produk yang cocok. <a href="?">Lihat semua produk</a>.</div>
  <?php endif; ?>
</div>
<?php toko_footer();
