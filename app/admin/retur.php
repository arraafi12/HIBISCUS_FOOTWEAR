<?php
require __DIR__ . '/../inc/admin.php';
require_admin();

$status = get('status');
$data = rows('SELECT r.*, pj.no_pesanan, pl.nama_pelanggan,
                     (SELECT SUM(jumlah) FROM detail_retur d WHERE d.id_retur = r.id_retur) AS pasang
                FROM retur r
                JOIN penjualan pj ON pj.id_penjualan = r.id_penjualan
                JOIN pelanggan pl ON pl.id_pelanggan = pj.id_pelanggan'
           . ($status ? ' WHERE r.status = ?' : '') . ' ORDER BY r.tanggal_pengajuan DESC, r.no_retur DESC',
           $status ? [$status] : []);

admin_header('Retur', 'retur');
page_title('Retur Barang');
?>
<div class="alert alert-light border small">
  Retur diajukan dari halaman detail pesanan berstatus <b>selesai</b> (maksimal 7 hari setelah diterima),
  baik oleh admin maupun oleh pelanggan di toko online.
</div>
<div class="card">
  <div class="card-body">
    <div class="btn-group btn-group-sm mb-3 flex-wrap">
      <?php foreach (['' => 'Semua', 'diajukan' => 'Diajukan', 'diterima' => 'Diterima', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak'] as $k => $t): ?>
        <a class="btn btn-outline-hibiscus <?= $status === $k ? 'active' : '' ?>" href="?status=<?= e($k) ?>"><?= e($t) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover">
        <thead class="table-light"><tr><th>No. Retur</th><th>Tanggal</th><th>Pesanan</th><th>Pelanggan</th><th>Jenis</th><th class="num">Pasang</th><th class="num">Refund</th><th>Status</th><th>Alasan</th></tr></thead>
        <tbody>
        <?php foreach ($data as $r): ?>
          <tr>
            <td><a href="retur_detail.php?id=<?= e($r['id_retur']) ?>"><?= e($r['no_retur']) ?></a></td>
            <td><?= e(tgl($r['tanggal_pengajuan'])) ?></td>
            <td><a href="penjualan_detail.php?id=<?= e($r['id_penjualan']) ?>"><?= e($r['no_pesanan']) ?></a></td>
            <td><?= e($r['nama_pelanggan']) ?></td>
            <td><?= e(label($r['jenis'])) ?></td>
            <td class="num"><?= angka($r['pasang']) ?></td>
            <td class="num"><?= $r['jenis'] === 'refund' ? rupiah($r['total_refund']) : '-' ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td class="small"><?= e($r['alasan']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data): ?><tr><td colspan="9" class="text-center text-muted py-4">Tidak ada retur.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer();
