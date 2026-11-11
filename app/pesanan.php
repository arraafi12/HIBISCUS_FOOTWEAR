<?php
require __DIR__ . '/inc/toko.php';
$me = require_pelanggan();

$id = (int) get('id', post('id'));
$pj = row('SELECT * FROM penjualan WHERE id_penjualan = ? AND id_pelanggan = ?', [$id, $me['id']]);
if (!$pj) {
    flash('warning', 'Pesanan tidak ditemukan.');
    redirect('akun.php');
}

if (is_post()) {
    csrf_check();
    try {
        switch (post('action')) {
            case 'bayar':
                bayar_pesanan($id, post('metode'), post('no_referensi'));
                flash('success', 'Pembayaran berhasil. Pesanan segera kami kirim.');
                break;
            case 'batal':
                if ($pj['status'] !== 'menunggu_pembayaran') {
                    throw new InvalidArgumentException('Pesanan yang sudah dibayar hanya bisa dibatalkan oleh admin.');
                }
                batalkan_pesanan($id, 'Dibatalkan pelanggan');
                flash('success', 'Pesanan dibatalkan.');
                break;
            case 'terima':
                terima_pesanan($id, date('Y-m-d'));
                flash('success', 'Terima kasih! Pesanan selesai.');
                break;
        }
    } catch (Throwable $e) {
        flash('danger', pesan_error($e));
    }
    redirect('pesanan.php?id=' . $id);
}

$items = rows('SELECT dp.*, pu.ukuran, p.nama_produk, k.nama_kategori
                 FROM detail_penjualan dp
                 JOIN produk_ukuran pu ON pu.id_varian = dp.id_varian
                 JOIN produk p ON p.id_produk = pu.id_produk
                 JOIN kategori k ON k.id_kategori = p.id_kategori
                WHERE dp.id_penjualan = ?', [$id]);
$bayar = row('SELECT * FROM pembayaran WHERE id_penjualan = ?', [$id]);
$kirim = row('SELECT * FROM pengiriman WHERE id_penjualan = ?', [$id]);
$retur = rows('SELECT * FROM retur WHERE id_penjualan = ? ORDER BY tanggal_pengajuan', [$id]);
$batas = batas_retur($id);
$st    = $pj['status'];

$langkah = ['menunggu_pembayaran' => 'Dipesan', 'dibayar' => 'Dibayar', 'dikirim' => 'Dikirim', 'selesai' => 'Selesai'];
$urutan  = array_search($st, array_keys($langkah), true);

toko_header('Pesanan ' . $pj['no_pesanan'], 'akun');
?>
<a class="small" href="akun.php">&larr; Pesanan saya</a>
<div class="d-flex flex-wrap justify-content-between align-items-center my-2 gap-2">
  <h1 class="h3 mb-0">Pesanan <?= e($pj['no_pesanan']) ?></h1>
  <?= status_badge($st) ?>
</div>

<?php if ($st !== 'dibatalkan'): ?>
<ol class="steps list-unstyled my-4" aria-label="Tahap pesanan">
  <?php $i = 0; foreach ($langkah as $k => $t): ?>
    <li><div class="<?= $i <= $urutan ? 'done' : '' ?>" <?= $i === $urutan ? 'aria-current="step"' : '' ?>><?= e($t) ?></div></li>
  <?php $i++; endforeach; ?>
</ol>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card mb-3">
      <ul class="list-group list-group-flush">
        <?php foreach ($items as $it): ?>
          <li class="list-group-item d-flex justify-content-between">
            <span><?= e($it['nama_produk']) ?> · uk. <?= e($it['ukuran']) ?> × <?= e($it['jumlah']) ?></span>
            <span><?= rupiah($it['subtotal']) ?></span>
          </li>
        <?php endforeach; ?>
        <li class="list-group-item d-flex justify-content-between"><span>Ongkir</span><span><?= rupiah($pj['ongkir']) ?></span></li>
        <li class="list-group-item d-flex justify-content-between fw-bold"><span>Total</span><span><?= rupiah($pj['total']) ?></span></li>
      </ul>
      <div class="card-footer small"><span class="text-muted">Dikirim ke</span> <?= e($pj['alamat_kirim']) ?></div>
    </div>

    <?php if ($retur): ?>
      <div class="card">
        <div class="card-header fw-semibold">Retur</div>
        <ul class="list-group list-group-flush">
          <?php foreach ($retur as $r): ?>
            <li class="list-group-item d-flex justify-content-between small">
              <span><?= e($r['no_retur']) ?> · <?= e(label($r['jenis'])) ?> · <?= e($r['alasan']) ?></span>
              <span><?= status_badge($r['status']) ?> <?= $r['total_refund'] > 0 ? rupiah($r['total_refund']) : '' ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-5">
    <?php if ($st === 'menunggu_pembayaran'): ?>
      <div class="card mb-3">
        <div class="card-header fw-semibold">Pembayaran</div>
        <div class="card-body">
          <p class="small">Total yang harus dibayar (lunas): <b><?= rupiah($pj['total']) ?></b></p>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="bayar">
            <div class="mb-2">
              <?php foreach (['transfer_bank' => 'Transfer Bank', 'e_wallet' => 'E-Wallet', 'qris' => 'QRIS', 'kartu_kredit' => 'Kartu Kredit'] as $v => $t): ?>
                <div class="form-check"><input class="form-check-input" type="radio" name="metode" id="m<?= $v ?>" value="<?= $v ?>" <?= $v === 'transfer_bank' ? 'checked' : '' ?>>
                  <label class="form-check-label" for="m<?= $v ?>"><?= $t ?></label></div>
              <?php endforeach; ?>
            </div>
            <input class="form-control form-control-sm mb-2" name="no_referensi" placeholder="No. referensi transaksi (opsional)">
            <button class="btn btn-hibiscus w-100">Bayar <?= rupiah($pj['total']) ?></button>
            <div class="form-text">Simulasi pembayaran untuk keperluan tugas, tanpa payment gateway sungguhan.</div>
          </form>
        </div>
      </div>
      <form method="post" data-confirm="Batalkan pesanan ini?">
        <?= csrf_field() ?><input type="hidden" name="action" value="batal">
        <button class="btn btn-outline-danger w-100">Batalkan Pesanan</button>
      </form>
    <?php endif; ?>

    <?php if ($bayar): ?>
      <div class="card mb-3"><div class="card-body small">
        Dibayar <?= rupiah($bayar['jumlah_bayar']) ?> via <?= e(label($bayar['metode'])) ?>
        pada <?= e(tgl($bayar['tanggal_bayar'], true)) ?>
      </div></div>
    <?php endif; ?>

    <?php if ($kirim): ?>
      <div class="card mb-3"><div class="card-body small">
        <div><?= e($kirim['kurir'] . ' ' . $kirim['layanan']) ?> · Resi <b><?= e($kirim['no_resi']) ?></b></div>
        <div class="text-muted">Dikirim <?= e(tgl($kirim['tanggal_kirim'])) ?><?= $kirim['tanggal_terima'] ? ' · Diterima ' . e(tgl($kirim['tanggal_terima'])) : '' ?></div>
        <?php if ($st === 'dikirim'): ?>
          <form method="post" class="mt-2" data-confirm="Konfirmasi pesanan sudah diterima?">
            <?= csrf_field() ?><input type="hidden" name="action" value="terima">
            <button class="btn btn-sm btn-success w-100">Pesanan Sudah Diterima</button>
          </form>
        <?php endif; ?>
      </div></div>
    <?php endif; ?>

    <?php if ($st === 'selesai' && $batas): ?>
      <div class="card"><div class="card-body small">
        <?php if (date('Y-m-d') <= $batas): ?>
          Ukuran tidak pas atau barang bermasalah? Ajukan retur sampai <b><?= e(tgl($batas)) ?></b>.
          <a class="btn btn-sm btn-outline-hibiscus w-100 mt-2" href="retur_ajukan.php?pesanan=<?= e($id) ?>">Ajukan Retur</a>
        <?php else: ?>
          Batas pengajuan retur sudah lewat (<?= e(tgl($batas)) ?>).
        <?php endif; ?>
      </div></div>
    <?php endif; ?>
  </div>
</div>
<?php toko_footer();
