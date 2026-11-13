<?php
require __DIR__ . '/inc/toko.php';
$me = require_pelanggan();

$isi = keranjang_detail();
if (!$isi) {
    redirect('keranjang.php');
}

// Hanya checkout item yang dipilih
$dipilih = $_SESSION['pilihan_keranjang'] ?? array_column($isi, 'id_varian');
$isi = array_values(array_filter($isi, fn($i) => in_array((int)$i['id_varian'], $dipilih, true)));
if (!$isi) {
    flash('warning', 'Pilih minimal 1 produk untuk checkout.');
    redirect('keranjang.php');
}
$pl = row('SELECT * FROM pelanggan WHERE id_pelanggan = ?', [$me['id']]);

if (is_post()) {
    csrf_check();
    $kota   = post('kota');
    $alamat = trim(post('alamat') . ', ' . $kota . ' ' . post('kode_pos'));
    try {
        if (post('alamat') === '' || $kota === '') {
            throw new InvalidArgumentException('Alamat dan kota tujuan wajib diisi.');
        }
        $id = buat_pesanan($me['id'], array_column($isi, 'jumlah', 'id_varian'), $alamat, ongkir_untuk($kota), post('catatan') ?: null);
        // Hapus dari keranjang hanya item yang sudah dipesan
        foreach ($isi as $item) {
            keranjang_set((int)$item['id_varian'], 0);
        }
        unset($_SESSION['pilihan_keranjang']);
        flash('success', 'Pesanan berhasil dibuat. Silakan lakukan pembayaran.');
        redirect('pesanan.php?id=' . $id);
    } catch (Throwable $e) {
        flash('danger', pesan_error($e), 'Gagal membuat pesanan');
        redirect('checkout.php');
    }
}

$subtotal = array_sum(array_column($isi, 'subtotal'));
$ongkir   = ongkir_untuk($pl['kota']);

toko_header('Checkout', 'keranjang');
?>
<h1 class="h3 mb-4">Checkout</h1>
<form method="post" class="row g-4">
  <?= csrf_field() ?>
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header fw-semibold">Alamat Pengiriman</div>
      <div class="card-body">
        <div class="mb-3"><label class="form-label">Penerima</label>
          <input class="form-control" value="<?= e($pl['nama_pelanggan'] . ' · ' . $pl['no_hp']) ?>" disabled></div>
        <div class="mb-3"><label class="form-label">Alamat <span class="text-danger">*</span></label>
          <textarea class="form-control" name="alamat" rows="2" required><?= e($pl['alamat']) ?></textarea></div>
        <div class="row g-2 mb-3">
          <div class="col-8"><label class="form-label">Kota <span class="text-danger">*</span></label>
            <input class="form-control" name="kota" id="kota" value="<?= e($pl['kota']) ?>" list="daftarKota" required></div>
          <div class="col-4"><label class="form-label">Kode Pos</label>
            <input class="form-control" name="kode_pos" value="<?= e($pl['kode_pos']) ?>"></div>
        </div>
        <datalist id="daftarKota">
          <?php foreach (array_keys(config('ongkir')) as $k) if ($k !== 'default') echo '<option value="' . e($k) . '">'; ?>
        </datalist>
        <div><label class="form-label">Catatan untuk penjual</label><input class="form-control" name="catatan" maxlength="255"></div>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header fw-semibold">Ringkasan Pesanan</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($isi as $i): ?>
          <li class="list-group-item d-flex justify-content-between small">
            <span><?= e($i['nama_produk']) ?> · uk. <?= e($i['ukuran']) ?> × <?= e($i['jumlah']) ?></span>
            <span><?= rupiah($i['subtotal']) ?></span>
          </li>
        <?php endforeach; ?>
        <li class="list-group-item d-flex justify-content-between"><span>Subtotal</span><span><?= rupiah($subtotal) ?></span></li>
        <li class="list-group-item d-flex justify-content-between"><span>Ongkir <small class="text-muted" id="ongkirKota">(<?= e($pl['kota']) ?>)</small></span>
          <span id="ongkir" data-map='<?= e(json_encode(config('ongkir'))) ?>'><?= rupiah($ongkir) ?></span></li>
        <li class="list-group-item d-flex justify-content-between fw-bold"><span>Total</span><span id="total" data-subtotal="<?= e($subtotal) ?>"><?= rupiah($subtotal + $ongkir) ?></span></li>
      </ul>
      <div class="card-body">
        <button class="btn btn-hibiscus w-100">Buat Pesanan</button>
        <div class="small text-muted mt-2">Stok langsung dipesankan untukmu. Pembayaran dilakukan sekali, lunas, di halaman berikutnya.</div>
      </div>
    </div>
  </div>
</form>
<script>
  (function () {
    var kota = document.getElementById('kota'), o = document.getElementById('ongkir'), t = document.getElementById('total');
    var map = JSON.parse(o.dataset.map), sub = parseFloat(t.dataset.subtotal);
    var rp = function (n) { return 'Rp ' + n.toLocaleString('id-ID'); };
    kota.addEventListener('input', function () {
      var v = map['default'];
      Object.keys(map).forEach(function (k) { if (k.toLowerCase() === kota.value.trim().toLowerCase()) v = map[k]; });
      o.textContent = rp(v); t.textContent = rp(sub + v);
      document.getElementById('ongkirKota').textContent = '(' + (kota.value || '-') + ')';
    });
  })();
</script>
<?php toko_footer();
