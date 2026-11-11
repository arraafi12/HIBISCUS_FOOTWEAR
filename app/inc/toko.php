<?php
/** Dimuat oleh setiap halaman toko online (pelanggan). */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/transaksi.php';

/** True jika kolom password pelanggan sudah ada (file 08_akun_pelanggan.sql sudah di-import). */
function akun_pelanggan_siap(): bool
{
    static $siap = null;
    return $siap ??= (bool) val("SELECT COUNT(*) FROM information_schema.columns
                                   WHERE table_schema = DATABASE() AND table_name = 'pelanggan' AND column_name = 'password_hash'");
}

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
function toko_header(string $title, string $active = ''): void
{
    $p = pelanggan();
    $n = keranjang_jumlah();
    $link = fn(string $key, string $href, string $text) =>
        '<a class="nav-link' . ($active === $key ? ' active' : '') . '" href="' . url($href) . '">' . $text . '</a>';
    ?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · Hibiscus Footwear</title>
  <?= head_assets() ?>
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-md topbar py-2">
  <div class="container">
    <a class="wordmark" href="<?= url('index.php') ?>">Hibiscus<span>Footwear</span></a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Buka menu"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <div class="navbar-nav ms-md-4 me-auto">
        <?= $link('katalog', 'index.php', 'Katalog') ?>
        <?php if ($p): ?><?= $link('akun', 'akun.php', 'Pesanan saya') ?><?php endif; ?>
      </div>
      <div class="navbar-nav align-items-md-center gap-md-1">
        <?= $link('keranjang', 'keranjang.php', 'Keranjang' . ($n ? ' <span class="mono cart-count">(' . $n . ')</span>' : '')) ?>
        <?php if ($p): ?>
          <span class="nav-link text-muted d-none d-md-inline">·</span>
          <?= $link('profil', 'akun.php#profil', e(explode(' ', $p['nama'])[0])) ?>
          <a class="nav-link" href="<?= url('keluar.php') ?>">Keluar</a>
        <?php else: ?>
          <?= $link('masuk', 'masuk.php', 'Masuk') ?>
          <a class="btn btn-sm btn-hibiscus ms-md-2 my-1 my-md-0" href="<?= url('daftar.php') ?>">Daftar</a>
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
<footer class="site-footer py-4 mt-4">
  <div class="container d-flex flex-wrap justify-content-between gap-2">
    <span>&copy; <?= date('Y') ?> Hibiscus Footwear</span>
    <span>Tukar ukuran atau refund dalam 7 hari setelah barang diterima · <a href="<?= url('admin/login.php') ?>" class="text-muted">Masuk staf</a></span>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/app.js') ?>"></script>
</body>
</html>
    <?php
}
