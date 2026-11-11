<?php
require __DIR__ . '/../inc/admin.php';
require_admin();

$status = get('status');
$cari   = get('q');
$where  = [];
$params = [];
if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($cari !== '') {
    $where[] = '(no_pesanan LIKE ? OR nama_pelanggan LIKE ?)';
    array_push($params, "%$cari%", "%$cari%");
}
$data = rows('SELECT * FROM v_laporan_penjualan' . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY tanggal_pesan DESC LIMIT 200', $params);

$statusList = ['' => 'Semua', 'menunggu_pembayaran' => 'Menunggu Bayar', 'dibayar' => 'Dibayar',
               'dikirim' => 'Dikirim', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];

admin_header('Penjualan', 'penjualan');
page_title('Penjualan', '<a class="btn btn-hibiscus" href="penjualan_tambah.php"><i class="bi bi-plus-lg"></i> Pesanan Baru</a>');
?>
<div class="card">
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2 mb-3">
      <div class="btn-group btn-group-sm flex-wrap">
        <?php foreach ($statusList as $k => $t): ?>
          <a class="btn btn-outline-hibiscus <?= $status === $k ? 'active' : '' ?>" href="?status=<?= e($k) ?>&q=<?= e(urlencode($cari)) ?>"><?= e($t) ?></a>
        <?php endforeach; ?>
      </div>
      <form class="d-flex gap-2 ms-auto" method="get">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <input class="form-control form-control-sm" name="q" value="<?= e($cari) ?>" placeholder="No. pesanan / pelanggan">
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover">
        <thead class="table-light"><tr><th>No. Pesanan</th><th>Tanggal</th><th>Pelanggan</th><th>Kota</th><th class="num">Pasang</th><th class="num">Total</th><th>Status</th><th>Bayar</th><th>Resi</th></tr></thead>
        <tbody>
        <?php foreach ($data as $r): ?>
          <tr>
            <td><a href="penjualan_detail.php?id=<?= e($r['id_penjualan']) ?>"><?= e($r['no_pesanan']) ?></a></td>
            <td><?= e(tgl($r['tanggal_pesan'], true)) ?></td>
            <td><?= e($r['nama_pelanggan']) ?></td>
            <td><?= e($r['kota']) ?></td>
            <td class="num"><?= angka($r['jumlah_pasang']) ?></td>
            <td class="num"><?= rupiah($r['total']) ?></td>
            <td><?= status_badge($r['status']) ?><?= $r['refund'] > 0 ? ' <span class="badge bg-light text-danger border">refund</span>' : '' ?></td>
            <td><?= e($r['metode_bayar'] ? label($r['metode_bayar']) : '-') ?></td>
            <td><?= e($r['no_resi'] ?: '-') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data): ?><tr><td colspan="9" class="text-center text-muted py-4">Tidak ada pesanan.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer();
