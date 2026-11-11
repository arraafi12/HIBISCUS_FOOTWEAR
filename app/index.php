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
$kategori = rows('SELECT * FROM kategori ORDER BY nama_kategori');
$terlaris = array_column(rows('SELECT kode_produk FROM v_produk_terlaris WHERE peringkat <= 2'), 'kode_produk');

toko_header('Katalog');
?>
<div class="hero p-4 p-md-5 mb-4">
  <h1 class="display-6 fw-bold mb-2">Sepatu buatan sendiri, dibuat dengan cinta.</h1>
  <p class="mb-0 opacity-75">Diproduksi langsung oleh HIBISCUS FOOTWEAR. Pilih ukuranmu, bayar sekali lunas, dan retur mudah dalam 7 hari.</p>
</div>

<div class="d-flex flex-wrap gap-2 align-items-center mb-4">
  <a class="btn btn-sm btn-outline-hibiscus <?= !$kat ? 'active' : '' ?>" href="?">Semua</a>
  <?php foreach ($kategori as $k): ?>
    <a class="btn btn-sm btn-outline-hibiscus <?= $kat === (int) $k['id_kategori'] ? 'active' : '' ?>" href="?kategori=<?= e($k['id_kategori']) ?>"><?= e($k['nama_kategori']) ?></a>
  <?php endforeach; ?>
  <form class="ms-auto d-flex gap-2" method="get">
    <?php if ($kat): ?><input type="hidden" name="kategori" value="<?= e($kat) ?>"><?php endif; ?>
    <input class="form-control form-control-sm" name="q" value="<?= e($cari) ?>" placeholder="Cari sepatu...">
    <button class="btn btn-sm btn-hibiscus"><i class="bi bi-search"></i></button>
  </form>
</div>

<div class="row g-4">
  <?php foreach ($produk as $p): ?>
    <div class="col-6 col-md-4 col-lg-3">
      <a class="card product-card h-100 text-reset text-decoration-none" href="produk.php?id=<?= e($p['id_produk']) ?>">
        <div class="product-img position-relative">
          <i class="bi <?= ikon_kategori($p['nama_kategori']) ?>"></i>
          <?php if (in_array($p['kode_produk'], $terlaris, true)): ?>
            <span class="badge bg-warning text-dark position-absolute top-0 start-0 m-2"><i class="bi bi-star-fill"></i> Terlaris</span>
          <?php endif; ?>
          <?php if ($p['stok'] == 0): ?><span class="badge bg-secondary position-absolute top-0 end-0 m-2">Stok habis</span><?php endif; ?>
        </div>
        <div class="card-body">
          <div class="small text-muted"><?= e($p['nama_kategori']) ?></div>
          <div class="fw-semibold"><?= e($p['nama_produk']) ?></div>
          <div class="text-hibiscus fw-bold"><?= rupiah($p['harga_jual']) ?></div>
          <div class="small text-muted mt-1"><?= $p['ukuran_tersedia'] ? 'Ukuran: ' . e(str_replace(',', ', ', $p['ukuran_tersedia'])) : 'Segera diproduksi' ?></div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
  <?php if (!$produk): ?><div class="col-12 text-center text-muted py-5">Produk tidak ditemukan.</div><?php endif; ?>
</div>
<?php toko_footer();
