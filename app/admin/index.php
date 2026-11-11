<?php
require __DIR__ . '/../inc/admin.php';
require_admin();

$periode = date('Y-m');
$rekap = row('SELECT * FROM v_rekap_penjualan_bulanan WHERE periode = ?', [$periode])
      ?: ['jumlah_transaksi' => 0, 'total_pasang' => 0, 'omzet_bersih' => 0];

$hitung = row("SELECT
    SUM(status = 'menunggu_pembayaran') AS menunggu,
    SUM(status = 'dibayar')             AS perlu_kirim,
    SUM(status = 'dikirim')             AS dikirim
  FROM penjualan");
$retur_baru  = (int) val("SELECT COUNT(*) FROM retur WHERE status IN ('diajukan','diterima')");
$produksi_jalan = (int) val("SELECT COUNT(*) FROM produksi WHERE status = 'proses'");

$stok_produk = rows("SELECT * FROM v_laporan_stok_produk WHERE status_stok <> 'AMAN' ORDER BY stok, kode_produk, ukuran LIMIT 10");
$stok_bahan  = rows("SELECT * FROM v_laporan_stok_bahan_baku WHERE status_stok <> 'AMAN' ORDER BY stok");
$pesanan     = rows('SELECT * FROM v_laporan_penjualan ORDER BY tanggal_pesan DESC LIMIT 6');
$terlaris    = rows('SELECT * FROM v_produk_terlaris ORDER BY peringkat LIMIT 5');

admin_header('Dashboard', 'index');
page_title('Dashboard', '<span class="text-muted small">' . e(tgl(date('Y-m-d'))) . '</span>');

$kpi = fn(string $label, string $value, string $href) =>
    '<a class="kpi" href="' . e($href) . '"><div class="kpi-label">' . e($label) . '</div><div class="kpi-value">' . $value . '</div></a>';
?>
<div class="kpis mb-4">
  <?= $kpi('Omzet bersih bulan ini', rupiah($rekap['omzet_bersih']), 'laporan.php?jenis=penjualan') ?>
  <?= $kpi('Menunggu pembayaran', angka($hitung['menunggu'] ?? 0), 'penjualan.php?status=menunggu_pembayaran') ?>
  <?= $kpi('Perlu dikirim', angka($hitung['perlu_kirim'] ?? 0), 'penjualan.php?status=dibayar') ?>
  <?= $kpi('Retur perlu diproses', angka($retur_baru), 'retur.php') ?>
</div>

<div class="row g-3">
  <div class="col-xl-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between">
        <span class="fw-semibold">Pesanan terbaru</span><a href="penjualan.php" class="small">Semua</a>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light"><tr><th>No. Pesanan</th><th>Pelanggan</th><th>Tanggal</th><th>Status</th><th class="num">Total</th></tr></thead>
          <tbody>
          <?php foreach ($pesanan as $p): ?>
            <tr>
              <td><a href="penjualan_detail.php?id=<?= e($p['id_penjualan']) ?>"><?= e($p['no_pesanan']) ?></a></td>
              <td><?= e($p['nama_pelanggan']) ?></td>
              <td><?= e(tgl($p['tanggal_pesan'])) ?></td>
              <td><?= status_badge($p['status']) ?></td>
              <td class="num"><?= rupiah($p['total']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between">
        <span class="fw-semibold">Produk terlaris</span><a href="laporan.php?jenis=terlaris" class="small">Laporan</a>
      </div>
      <ul class="list-group list-group-flush">
        <?php foreach ($terlaris as $t): ?>
          <li class="list-group-item d-flex justify-content-between">
            <span><span class="mono text-muted me-2"><?= e(str_pad((string) $t['peringkat'], 2, '0', STR_PAD_LEFT)) ?></span><?= e($t['nama_produk']) ?></span>
            <span class="text-muted"><?= angka($t['total_terjual']) ?> pasang</span>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="card-footer small text-muted">
        Produksi berjalan: <b><?= $produksi_jalan ?></b> · Sedang dikirim: <b><?= angka($hitung['dikirim'] ?? 0) ?></b>
      </div>
    </div>
  </div>

  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header fw-semibold">Stok produk menipis / habis</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr><th>SKU</th><th>Produk</th><th class="num">Stok</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($stok_produk as $s): ?>
            <tr><td><?= e($s['sku']) ?></td><td><?= e($s['nama_produk']) ?> (<?= e($s['ukuran']) ?>)</td>
                <td class="num"><?= angka($s['stok']) ?></td><td><?= status_badge($s['status_stok']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$stok_produk): ?><tr><td colspan="4" class="text-center text-muted py-3">Semua stok aman.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header fw-semibold">Stok bahan baku menipis / habis</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr><th>Bahan</th><th class="num">Stok</th><th class="num">Minimum</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($stok_bahan as $s): ?>
            <tr><td><?= e($s['nama_bahan']) ?></td>
                <td class="num"><?= angka($s['stok']) ?> <?= e($s['satuan']) ?></td>
                <td class="num"><?= angka($s['stok_minimum']) ?></td>
                <td><?= status_badge($s['status_stok']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$stok_bahan): ?><tr><td colspan="4" class="text-center text-muted py-3">Semua stok aman.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php admin_footer();
