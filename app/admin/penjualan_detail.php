<?php
require __DIR__ . '/../inc/admin.php';
$u = require_admin();

$id = (int) get('id', post('id'));
$pj = row('SELECT pj.*, pl.nama_pelanggan, pl.email, pl.no_hp
             FROM penjualan pj JOIN pelanggan pl ON pl.id_pelanggan = pj.id_pelanggan
            WHERE pj.id_penjualan = ?', [$id]);
if (!$pj) {
    flash('warning', 'Pesanan tidak ditemukan.');
    redirect('admin/penjualan.php');
}

if (is_post()) {
    csrf_check();
    try {
        switch (post('action')) {
            case 'bayar':
                bayar_pesanan($id, post('metode'), post('no_referensi'));
                flash('success', 'Pembayaran lunas dicatat.');
                break;
            case 'kirim':
                kirim_pesanan($id, post('kurir'), post('layanan'), post('no_resi'), post('tanggal_kirim'), $u['id']);
                flash('success', 'Pengiriman dicatat. Status pesanan: dikirim.');
                break;
            case 'terima':
                terima_pesanan($id, post('tanggal_terima'));
                flash('success', 'Pesanan diterima pelanggan. Status: selesai.');
                break;
            case 'batal':
                batalkan_pesanan($id, post('alasan') ?: 'Dibatalkan admin');
                flash('success', 'Pesanan dibatalkan dan stok dikembalikan.'
                    . ($pj['status'] === 'dibayar' ? ' Jangan lupa kembalikan dana pelanggan.' : ''));
                break;
        }
    } catch (Throwable $e) {
        flash('danger', pesan_error($e), ['bayar' => 'Gagal mencatat pembayaran', 'kirim' => 'Gagal mencatat pengiriman', 'terima' => 'Gagal menandai pesanan diterima', 'batal' => 'Gagal membatalkan pesanan'][post('action')] ?? null);
    }
    redirect('admin/penjualan_detail.php?id=' . $id);
}

$items = rows('SELECT dp.*, pu.ukuran, pu.sku, p.nama_produk
                 FROM detail_penjualan dp
                 JOIN produk_ukuran pu ON pu.id_varian = dp.id_varian
                 JOIN produk p ON p.id_produk = pu.id_produk
                WHERE dp.id_penjualan = ? ORDER BY p.nama_produk, pu.ukuran', [$id]);
$bayar = row('SELECT * FROM pembayaran WHERE id_penjualan = ?', [$id]);
$kirim = row('SELECT pg.*, u.nama_lengkap FROM pengiriman pg JOIN pengguna u ON u.id_pengguna = pg.id_pengguna WHERE id_penjualan = ?', [$id]);
$retur = rows('SELECT * FROM retur WHERE id_penjualan = ? ORDER BY tanggal_pengajuan', [$id]);
$batas = batas_retur($id);
$st    = $pj['status'];

admin_header('Pesanan ' . $pj['no_pesanan'], 'penjualan');
page_title('Pesanan ' . $pj['no_pesanan'],
    '<button class="btn btn-outline-secondary" onclick="window.print()">Cetak</button>'
    . '<a class="btn btn-outline-secondary" href="penjualan.php">Kembali</a>');
?>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between mb-3">
          <div>
            <div class="fw-semibold"><?= e($pj['nama_pelanggan']) ?></div>
            <div class="small text-muted"><?= e($pj['email']) ?> · <?= e($pj['no_hp']) ?></div>
            <div class="small"><?= e($pj['alamat_kirim']) ?></div>
          </div>
          <div class="text-end">
            <?= status_badge($st) ?>
            <div class="small text-muted mt-1"><?= e(tgl($pj['tanggal_pesan'], true)) ?></div>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead class="table-light"><tr><th>Produk</th><th>Ukuran</th><th>SKU</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
              <tr><td><?= e($it['nama_produk']) ?></td><td><?= e($it['ukuran']) ?></td><td><?= e($it['sku']) ?></td>
                  <td class="num"><?= angka($it['jumlah']) ?></td><td class="num"><?= rupiah($it['harga_satuan']) ?></td>
                  <td class="num"><?= rupiah($it['subtotal']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr><td colspan="5" class="text-end">Subtotal</td><td class="num"><?= rupiah($pj['subtotal']) ?></td></tr>
              <tr><td colspan="5" class="text-end">Ongkir</td><td class="num"><?= rupiah($pj['ongkir']) ?></td></tr>
              <tr class="fw-bold"><td colspan="5" class="text-end">Total</td><td class="num"><?= rupiah($pj['total']) ?></td></tr>
            </tfoot>
          </table>
        </div>
        <?php if ($pj['catatan']): ?><div class="small text-muted">Catatan: <?= e($pj['catatan']) ?></div><?php endif; ?>
      </div>
    </div>

    <?php if ($retur): ?>
    <div class="card mb-3">
      <div class="card-header fw-semibold">Retur</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($retur as $r): ?>
          <li class="list-group-item d-flex justify-content-between">
            <span><a href="retur_detail.php?id=<?= e($r['id_retur']) ?>"><?= e($r['no_retur']) ?></a> · <?= e(label($r['jenis'])) ?> · <?= e($r['alasan']) ?></span>
            <span><?= status_badge($r['status']) ?> <?= $r['total_refund'] > 0 ? rupiah($r['total_refund']) : '' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-xl-4 d-print-none">
    <!-- 1. Pembayaran -->
    <div class="card mb-3">
      <div class="card-header fw-semibold">Pembayaran</div>
      <div class="card-body">
        <?php if ($bayar): ?>
          <div><?= e(label($bayar['metode'])) ?> · <?= rupiah($bayar['jumlah_bayar']) ?></div>
          <div class="small text-muted"><?= e(tgl($bayar['tanggal_bayar'], true)) ?> · Ref: <?= e($bayar['no_referensi'] ?: '-') ?></div>
        <?php elseif ($st === 'menunggu_pembayaran'): ?>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="bayar">
            <select class="form-select form-select-sm mb-2" name="metode" required>
              <option value="transfer_bank">Transfer Bank</option><option value="e_wallet">E-Wallet</option>
              <option value="qris">QRIS</option><option value="kartu_kredit">Kartu Kredit</option>
            </select>
            <input class="form-control form-control-sm mb-2" name="no_referensi" placeholder="No. referensi / bukti">
            <button class="btn btn-sm btn-success w-100">Catat Lunas <?= rupiah($pj['total']) ?></button>
          </form>
        <?php else: ?><span class="text-muted small">Tidak ada pembayaran.</span><?php endif; ?>
      </div>
    </div>

    <!-- 2. Pengiriman -->
    <div class="card mb-3">
      <div class="card-header fw-semibold">Pengiriman</div>
      <div class="card-body">
        <?php if ($kirim): ?>
          <div><?= e($kirim['kurir'] . ' ' . $kirim['layanan']) ?> · <b><?= e($kirim['no_resi']) ?></b></div>
          <div class="small text-muted">Dikirim <?= e(tgl($kirim['tanggal_kirim'])) ?> oleh <?= e($kirim['nama_lengkap']) ?></div>
          <?php if ($kirim['tanggal_terima']): ?>
            <div class="small text-success">Diterima <?= e(tgl($kirim['tanggal_terima'])) ?></div>
          <?php else: ?>
            <form method="post" class="d-flex gap-2 mt-2">
              <?= csrf_field() ?><input type="hidden" name="action" value="terima">
              <input class="form-control form-control-sm" type="date" name="tanggal_terima" value="<?= date('Y-m-d') ?>" min="<?= e($kirim['tanggal_kirim']) ?>" required>
              <button class="btn btn-sm btn-success text-nowrap">Sudah Diterima</button>
            </form>
          <?php endif; ?>
        <?php elseif ($st === 'dibayar'): ?>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="kirim">
            <div class="row g-2 mb-2">
              <div class="col-7"><input class="form-control form-control-sm" name="kurir" placeholder="Kurir (JNE, J&T, ...)" required></div>
              <div class="col-5"><input class="form-control form-control-sm" name="layanan" placeholder="Layanan"></div>
            </div>
            <input class="form-control form-control-sm mb-2" name="no_resi" placeholder="No. resi" required>
            <input class="form-control form-control-sm mb-2" type="date" name="tanggal_kirim" value="<?= date('Y-m-d') ?>" required>
            <button class="btn btn-sm btn-primary w-100">Catat Pengiriman</button>
          </form>
        <?php else: ?><span class="text-muted small">Belum bisa dikirim.</span><?php endif; ?>
      </div>
    </div>

    <!-- 3. Retur / Batal -->
    <?php if ($st === 'selesai' && $batas): ?>
      <div class="card mb-3"><div class="card-body">
        <?php if (date('Y-m-d') <= $batas): ?>
          <a class="btn btn-sm btn-outline-hibiscus w-100" href="retur_tambah.php?penjualan=<?= e($id) ?>">Ajukan Retur</a>
          <div class="small text-muted mt-1">Batas retur: <?= e(tgl($batas)) ?></div>
        <?php else: ?>
          <span class="small text-muted">Batas retur sudah lewat (<?= e(tgl($batas)) ?>).</span>
        <?php endif; ?>
      </div></div>
    <?php endif; ?>
    <?php if (in_array($st, ['menunggu_pembayaran', 'dibayar'], true)): ?>
      <div class="card border-danger"><div class="card-body">
        <form method="post" data-confirm="Batalkan pesanan ini? Stok akan dikembalikan.">
          <?= csrf_field() ?><input type="hidden" name="action" value="batal">
          <input class="form-control form-control-sm mb-2" name="alasan" placeholder="Alasan pembatalan">
          <button class="btn btn-sm btn-outline-danger w-100">Batalkan Pesanan</button>
        </form>
      </div></div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer();
