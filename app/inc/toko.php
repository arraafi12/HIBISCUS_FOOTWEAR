<?php
/** Dimuat oleh setiap halaman toko online (pelanggan). */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/transaksi.php';

// ---------------------------------------------------------------------
//  Keranjang belanja (disimpan di sesi): [id_varian => jumlah]
// ---------------------------------------------------------------------
function keranjang(): array
{
    return $_SESSION['keranjang'] ?? [];
}

function keranjang_set(int $id_varian, int $jumlah): void
{
    if ($jumlah <= 0) {
        unset($_SESSION['keranjang'][$id_varian]);
    } else {
        $_SESSION['keranjang'][$id_varian] = $jumlah;
    }
}

function keranjang_jumlah(): int
{
    return array_sum(keranjang());
}

/** Isi keranjang lengkap dengan data produk, harga, dan stok terkini. */
function keranjang_detail(): array
{
    $k = keranjang();
    if (!$k) {
        return [];
    }
    $in = implode(',', array_fill(0, count($k), '?'));
    $data = rows("SELECT pu.id_varian, pu.ukuran, pu.sku, pu.stok, p.id_produk, p.nama_produk, p.harga_jual, p.aktif, k.nama_kategori
                    FROM produk_ukuran pu JOIN produk p ON p.id_produk = pu.id_produk
                    JOIN kategori k ON k.id_kategori = p.id_kategori
                   WHERE pu.id_varian IN ($in)", array_keys($k));
    foreach ($data as &$d) {
        $d['jumlah']   = $k[$d['id_varian']];
        $d['subtotal'] = $d['jumlah'] * $d['harga_jual'];
    }
    return $data;
}

// ---------------------------------------------------------------------
//  Tampilan
// ---------------------------------------------------------------------
function toko_header(string $title): void
{
    $p = pelanggan();
    $app = config('app_name');
    $n = keranjang_jumlah();
    ?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · <?= e($app) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= url('assets/app.css') ?>">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-md navbar-dark bg-hibiscus">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= url('index.php') ?>"><i class="bi bi-flower2"></i> <?= e($app) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="Menu"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= url('index.php') ?>">Katalog</a></li>
        <?php if ($p): ?><li class="nav-item"><a class="nav-link" href="<?= url('akun.php') ?>">Pesanan Saya</a></li><?php endif; ?>
      </ul>
      <div class="d-flex align-items-center gap-2">
        <a class="btn btn-light position-relative" href="<?= url('keranjang.php') ?>" aria-label="Keranjang">
          <i class="bi bi-bag"></i>
          <?php if ($n): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-dark"><?= $n ?></span><?php endif; ?>
        </a>
        <?php if ($p): ?>
          <a class="btn btn-outline-light" href="<?= url('akun.php') ?>"><i class="bi bi-person-circle"></i> <?= e(explode(' ', $p['nama'])[0]) ?></a>
          <a class="btn btn-outline-light" href="<?= url('keluar.php') ?>">Keluar</a>
        <?php else: ?>
          <a class="btn btn-outline-light" href="<?= url('masuk.php') ?>">Masuk</a>
          <a class="btn btn-light" href="<?= url('daftar.php') ?>">Daftar</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
<main class="container py-4 flex-grow-1">
  <?= render_flash() ?>
    <?php
}

function toko_footer(): void
{
    ?>
</main>
<footer class="bg-white border-top py-3 small text-muted">
  <div class="container d-flex flex-wrap justify-content-between gap-2">
    <span>&copy; <?= date('Y') ?> <?= e(config('app_name')) ?> · Sepatu buatan sendiri</span>
    <span>Retur maks. 7 hari setelah diterima · <a href="<?= url('admin/login.php') ?>" class="text-muted">Admin</a></span>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/app.js') ?>"></script>
</body>
</html>
    <?php
}
