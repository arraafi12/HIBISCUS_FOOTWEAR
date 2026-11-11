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
  <title><?= e($title) ?> · Admin Hibiscus Footwear</title>
  <?= head_assets() ?>
</head>
<body class="admin">
<nav class="navbar topbar sticky-top d-print-none py-2">
  <div class="container-fluid px-md-4">
    <button class="btn btn-sm btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas"
            data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Buka menu">Menu</button>
    <a class="wordmark" href="<?= url('admin/index.php') ?>">Hibiscus<span>Admin</span></a>
    <div class="ms-auto d-flex align-items-center gap-3 small">
      <a href="<?= url('index.php') ?>" target="_blank" rel="noopener">Lihat toko ↗</a>
      <span class="d-none d-md-inline text-muted"><?= e($u['nama']) ?></span>
      <a href="<?= url('admin/logout.php') ?>">Keluar</a>
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
        <nav class="offcanvas-body d-block p-0 pb-4" aria-label="Menu admin">
          <?php foreach (admin_menu() as [$grup, $items]): ?>
            <?php if ($grup !== 'Dashboard'): ?><div class="sidebar-group"><?= e($grup) ?></div><?php endif; ?>
            <?php foreach ($items as [$key, $text, $icon, $href]): ?>
              <a class="sidebar-link <?= $key === $active ? 'active' : '' ?>" href="<?= url('admin/' . $href) ?>"
                 <?= $key === $active ? 'aria-current="page"' : '' ?>><?= e($text) ?></a>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </nav>
      </div>
    </aside>
    <main class="col-lg-10 py-4 px-md-4">
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
        . '<h1 class="page-title mb-0">' . e($title) . '</h1><div class="d-flex gap-2 d-print-none">' . $actions . '</div></div>';
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
