<?php
require __DIR__ . '/../inc/admin.php';
require_admin();

$awal  = get('awal', date('Y-m-01', strtotime('-2 months')));
$akhir = get('akhir', date('Y-m-d'));

$data = rows('SELECT pb.*, s.nama_supplier, pg.nama_lengkap,
                     (SELECT COUNT(*) FROM detail_pembelian d WHERE d.id_pembelian = pb.id_pembelian) AS jumlah_item
                FROM pembelian pb
                JOIN supplier s  ON s.id_supplier = pb.id_supplier
                JOIN pengguna pg ON pg.id_pengguna = pb.id_pengguna
               WHERE pb.tanggal BETWEEN ? AND ?
               ORDER BY pb.tanggal DESC, pb.no_faktur DESC', [$awal, $akhir]);

admin_header('Pembelian', 'pembelian');
page_title('Pembelian Bahan Baku', '<a class="btn btn-hibiscus" href="pembelian_tambah.php">Pembelian Baru</a>');
?>
<div class="card">
  <div class="card-body">
    <form class="row g-2 mb-3" method="get">
      <div class="col-auto"><input class="form-control" type="date" name="awal" value="<?= e($awal) ?>"></div>
      <div class="col-auto"><input class="form-control" type="date" name="akhir" value="<?= e($akhir) ?>"></div>
      <div class="col-auto"><button class="btn btn-outline-secondary">Tampilkan</button></div>
    </form>
    <div class="table-responsive">
      <table class="table table-sm table-hover">
        <thead class="table-light"><tr><th>No. Faktur</th><th>Tanggal</th><th>Supplier</th><th class="num">Item</th><th class="num">Total</th><th>Dicatat oleh</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($data as $r): ?>
          <tr>
            <td><a href="pembelian_detail.php?id=<?= e($r['id_pembelian']) ?>"><?= e($r['no_faktur']) ?></a></td>
            <td><?= e(tgl($r['tanggal'])) ?></td>
            <td><?= e($r['nama_supplier']) ?></td>
            <td class="num"><?= e($r['jumlah_item']) ?></td>
            <td class="num"><?= rupiah($r['total']) ?></td>
            <td><?= e($r['nama_lengkap']) ?></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="pembelian_detail.php?id=<?= e($r['id_pembelian']) ?>">Detail</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data): ?><tr><td colspan="7" class="text-center text-muted py-4">Tidak ada pembelian pada periode ini.</td></tr><?php endif; ?>
        </tbody>
        <?php if ($data): ?>
        <tfoot><tr class="fw-semibold"><td colspan="4">Total</td><td class="num"><?= rupiah(array_sum(array_column($data, 'total'))) ?></td><td colspan="2"></td></tr></tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>
<?php admin_footer();
