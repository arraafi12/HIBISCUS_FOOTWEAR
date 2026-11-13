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
                $bukti = null;
                $f = $_FILES['bukti_bayar'] ?? null;
                if ($f && $f['error'] === UPLOAD_ERR_OK) {
                    $mime = mime_content_type($f['tmp_name']);
                    if (!in_array($mime, ['image/jpeg','image/png','image/webp','application/pdf'], true)) {
                        throw new InvalidArgumentException('Format bukti bayar tidak valid. Gunakan JPG, PNG, WebP, atau PDF.');
                    }
                    if ($f['size'] > 3 * 1024 * 1024) {
                        throw new InvalidArgumentException('Ukuran bukti bayar maksimal 3 MB.');
                    }
                    $bukti = ['data' => file_get_contents($f['tmp_name']), 'mime' => $mime];
                }
                bayar_pesanan($id, post('metode'), post('no_referensi'), $bukti);
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
        flash('danger', pesan_error($e), ['bayar' => 'Pembayaran gagal', 'batal' => 'Gagal membatalkan pesanan', 'terima' => 'Gagal mengonfirmasi pesanan'][post('action')] ?? null);
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
          <form method="post" enctype="multipart/form-data" id="formBayar">
            <?= csrf_field() ?><input type="hidden" name="action" value="bayar">
            <div class="mb-3">
              <?php
              $metodes = [
                  'transfer_bank' => 'Transfer Bank',
                  'e_wallet'      => 'E-Wallet',
                  'kartu_kredit'  => 'Kartu Kredit',
              ];
              $info = [
                  'transfer_bank' => [
                      'BCA'     => '1234567890',
                      'Mandiri' => '1550098765432',
                      'BNI'     => '0987654321',
                      'BRI'     => '123456789012345',
                  ],
                  'e_wallet' => [
                      'GoPay'   => '628112345678',
                      'OVO'     => '628987654321',
                      'Dana'    => '628561234567',
                      'ShopeePay' => '628211234567',
                  ],
                  'kartu_kredit' => [
                      'Visa'       => '4111 1111 1111 1111',
                      'Mastercard' => '5500 0000 0000 0004',
                  ],
              ];
              foreach ($metodes as $v => $t):
              ?>
                <div class="form-check">
                  <input class="form-check-input js-metode" type="radio" name="metode" id="m<?= $v ?>" value="<?= $v ?>" <?= $v === 'transfer_bank' ? 'checked' : '' ?>>
                  <label class="form-check-label" for="m<?= $v ?>"><?= $t ?></label>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- Info nomor tujuan per metode -->
            <?php foreach ($info as $met => $akun): ?>
            <div class="js-info-metode mb-3 p-3 bg-light rounded small" id="info-<?= $met ?>" style="display:<?= $met === 'transfer_bank' ? 'block' : 'none' ?>">
              <?php if ($met === 'transfer_bank'): ?>
                <div class="fw-semibold mb-1">Transfer ke rekening:</div>
              <?php elseif ($met === 'e_wallet'): ?>
                <div class="fw-semibold mb-1">Nomor dompet digital:</div>
              <?php else: ?>
                <div class="fw-semibold mb-1">Nomor kartu (simulasi):</div>
              <?php endif; ?>
              <table class="table table-sm mb-0">
                <?php foreach ($akun as $nama => $nomor): ?>
                <tr><td class="text-muted pe-3"><?= $nama ?></td><td class="fw-semibold font-monospace"><?= $nomor ?></td></tr>
                <?php endforeach; ?>
              </table>
              <?php if ($met === 'kartu_kredit'): ?>
                <div class="text-muted mt-1">CVV: 123 &nbsp;·&nbsp; Expired: 12/28</div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div class="mb-2">
              <label class="form-label small" for="no_referensi">No. Referensi Transaksi <span class="text-muted">(opsional)</span></label>
              <input class="form-control form-control-sm" id="no_referensi" name="no_referensi" placeholder="Contoh: TRF202611130001">
            </div>
            <div class="mb-3">
              <label class="form-label small" for="bukti_bayar">Upload Bukti Pembayaran <span class="text-muted">(opsional, maks. 3 MB)</span></label>
              <input class="form-control form-control-sm" type="file" id="bukti_bayar" name="bukti_bayar" accept="image/jpeg,image/png,image/webp,application/pdf">
              <div class="form-text">JPG, PNG, WebP, atau PDF.</div>
            </div>
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
        <?php if ($bayar['bukti_bayar']): ?>
          <div class="mt-2">
            <span class="text-muted">Bukti bayar:</span>
            <?php if (str_starts_with($bayar['tipe_bukti'] ?? '', 'image/')): ?>
              <div class="mt-1">
                <a href="#modalBuktiPelanggan" data-bs-toggle="modal" data-bs-target="#modalBuktiPelanggan" title="Klik untuk memperbesar">
                  <img src="bukti_bayar.php?id=<?= e($bayar['id_pembayaran']) ?>"
                       class="img-fluid rounded border shadow-sm" style="max-height:180px; cursor:pointer;" alt="Bukti bayar">
                </a>
                <div class="text-muted small mt-1" style="font-size:0.75rem">Klik gambar untuk memperbesar</div>

                <div class="modal fade" id="modalBuktiPelanggan" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                      <div class="modal-header py-2">
                        <h6 class="modal-title m-0">Bukti Pembayaran · <?= e($pj['no_pesanan']) ?></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                      </div>
                      <div class="modal-body text-center p-2 bg-light">
                        <img src="bukti_bayar.php?id=<?= e($bayar['id_pembayaran']) ?>"
                             class="img-fluid rounded shadow-sm" style="max-height:80vh; object-fit:contain;" alt="Bukti bayar">
                      </div>
                      <div class="modal-footer py-2">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php else: ?>
              <a href="bukti_bayar.php?id=<?= e($bayar['id_pembayaran']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary mt-1">📄 Lihat PDF</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
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
<script>
(function () {
  var radios = document.querySelectorAll('.js-metode');
  function toggleInfo() {
    var val = '';
    radios.forEach(function(r) { if (r.checked) val = r.value; });
    document.querySelectorAll('.js-info-metode').forEach(function(el) {
      el.style.display = el.id === 'info-' + val ? 'block' : 'none';
    });
  }
  radios.forEach(function(r) { r.addEventListener('change', toggleInfo); });
  toggleInfo();
})();
</script>
<?php toko_footer();
