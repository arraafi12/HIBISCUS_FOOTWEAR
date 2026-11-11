<?php
/** Tampilan (header, sidebar, footer) halaman admin. */

function admin_menu(): array
{
    return [
        ['Dashboard', [['index', 'Dashboard', 'bi-speedometer2', 'index.php']]],
        ['Master', [
            ['kategori',  'Kategori',   'bi-tags',          'kategori.php'],
            ['produk',    'Produk',     'bi-bag',           'produk.php'],
            ['bahan',     'Bahan Baku', 'bi-box-seam',      'bahan.php'],
            ['supplier',  'Supplier',   'bi-truck',         'supplier.php'],
            ['pelanggan', 'Pelanggan',  'bi-people',        'pelanggan.php'],
            ['pengguna',  'Pengguna',   'bi-person-badge',  'pengguna.php'],
        ]],
        ['Transaksi', [
            ['pembelian', 'Pembelian',  'bi-cart-plus',     'pembelian.php'],
            ['produksi',  'Produksi',   'bi-tools',         'produksi.php'],
            ['penjualan', 'Penjualan',  'bi-receipt',       'penjualan.php'],
            ['retur',     'Retur',      'bi-arrow-return-left', 'retur.php'],
        ]],
        ['Laporan', [
            ['lap_pembelian',  'Pembelian',        'bi-file-earmark-text', 'laporan.php?jenis=pembelian'],
            ['lap_stok_bahan', 'Stok Bahan Baku',  'bi-file-earmark-text', 'laporan.php?jenis=stok_bahan'],
            ['lap_stok_produk','Stok Produk Jadi', 'bi-file-earmark-text', 'laporan.php?jenis=stok_produk'],
            ['lap_produksi',   'Produksi',         'bi-file-earmark-text', 'laporan.php?jenis=produksi'],
            ['lap_penjualan',  'Penjualan',        'bi-file-earmark-text', 'laporan.php?jenis=penjualan'],
            ['lap_terlaris',   'Produk Terlaris',  'bi-file-earmark-text', 'laporan.php?jenis=terlaris'],
            ['lap_retur',      'Retur',            'bi-file-earmark-text', 'laporan.php?jenis=retur'],
        ]],
    ];
}

function admin_header(string $title, string $active = ''): void
{
    $u = require_admin();
    $app = config('app_name');
    ?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · Admin <?= e($app) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= url('assets/app.css') ?>">
</head>
<body class="admin">
<nav class="navbar navbar-dark bg-hibiscus sticky-top d-print-none">
  <div class="container-fluid">
    <button class="btn btn-outline-light d-lg-none me-2" type="button" data-bs-toggle="offcanvas"
            data-bs-target="#sidebar" aria-label="Menu"><i class="bi bi-list"></i></button>
    <a class="navbar-brand fw-bold" href="<?= url('admin/index.php') ?>"><i class="bi bi-flower2"></i> <?= e($app) ?> <small class="fw-normal opacity-75">Admin</small></a>
    <div class="ms-auto d-flex align-items-center gap-2 text-white">
      <a class="btn btn-sm btn-outline-light" href="<?= url('index.php') ?>" target="_blank"><i class="bi bi-shop"></i><span class="d-none d-sm-inline"> Lihat Toko</span></a>
      <span class="d-none d-md-inline small"><i class="bi bi-person-circle"></i> <?= e($u['nama']) ?></span>
      <a class="btn btn-sm btn-light" href="<?= url('admin/logout.php') ?>">Keluar</a>
    </div>
  </div>
</nav>
<div class="container-fluid">
  <div class="row">
    <aside class="col-lg-2 p-0 d-print-none">
      <div class="offcanvas-lg offcanvas-start sidebar" tabindex="-1" id="sidebar">
        <div class="offcanvas-header d-lg-none">
          <h5 class="offcanvas-title">Menu</h5>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Tutup"></button>
        </div>
        <div class="offcanvas-body d-block p-2">
          <?php foreach (admin_menu() as [$grup, $items]): ?>
            <div class="sidebar-group"><?= e($grup) ?></div>
            <?php foreach ($items as [$key, $text, $icon, $href]): ?>
              <a class="sidebar-link <?= $key === $active ? 'active' : '' ?>" href="<?= url('admin/' . $href) ?>">
                <i class="bi <?= $icon ?>"></i> <?= e($text) ?>
              </a>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </aside>
    <main class="col-lg-10 py-3 px-md-4">
      <?= render_flash() ?>
    <?php
}

function admin_footer(): void
{
    ?>
    </main>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/app.js') ?>"></script>
</body>
</html>
    <?php
}

/** Judul halaman + tombol aksi di kanan. */
function page_title(string $title, string $actions = ''): void
{
    echo '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">'
        . '<h1 class="h4 mb-0">' . e($title) . '</h1><div class="d-flex gap-2 d-print-none">' . $actions . '</div></div>';
}

/** Tombol hapus kecil dalam form POST dengan konfirmasi. */
function delete_button(string $action, array $fields, string $confirm = 'Hapus data ini?', string $text = '<i class="bi bi-trash"></i>'): string
{
    $h = '<form method="post" action="' . e($action) . '" class="d-inline" data-confirm="' . e($confirm) . '">' . csrf_field();
    foreach ($fields as $k => $v) {
        $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    return $h . '<button class="btn btn-sm btn-outline-danger" title="Hapus">' . $text . '</button></form>';
}
