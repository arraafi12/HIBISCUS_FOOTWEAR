<?php
require __DIR__ . '/inc/toko.php';

if (is_post()) {
    csrf_check();
    $idv = (int) post('id_varian');
    $stok = (int) val('SELECT pu.stok FROM produk_ukuran pu JOIN produk p ON p.id_produk = pu.id_produk WHERE pu.id_varian = ? AND p.aktif = 1', [$idv]);
    switch (post('action')) {
        case 'tambah':
            $jml = (keranjang()[$idv] ?? 0) + max(1, (int) post('jumlah', 1));
            if ($stok <= 0) {
                flash('danger', 'Stok ukuran ini sedang habis.', 'Gagal menambahkan ke keranjang');
            } else {
                if ($jml > $stok) {
                    $jml = $stok;
                    flash('warning', "Jumlah disesuaikan dengan stok yang tersedia ($stok pasang).");
                } else {
                    flash('success', 'Produk ditambahkan ke keranjang.');
                }
                keranjang_set($idv, $jml);
            }
            break;
        case 'ubah':
            $jml   = max(1, (int) post('jumlah'));
            $pesan = '';
            if ($jml > $stok) {
                $jml   = max(0, $stok);
                $pesan = "Stok ukuran ini tersedia hanya $stok pasang, jumlah disesuaikan.";
            }
            keranjang_set($idv, $jml);
            // Permintaan dari JavaScript: balas JSON tanpa memuat ulang halaman
            if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') {
                $isi = keranjang_detail();
                $baris = array_values(array_filter($isi, fn($i) => (int) $i['id_varian'] === $idv))[0] ?? null;
                header('Content-Type: application/json');
                echo json_encode([
                    'jumlah'   => $baris['jumlah'] ?? 0,
                    'subtotal' => rupiah($baris['subtotal'] ?? 0),
                    'total'    => rupiah(array_sum(array_column($isi, 'subtotal'))),
                    'pasang'   => keranjang_jumlah(),
                    'pesan'    => $pesan,
                ]);
                exit;
            }
            if ($pesan) {
                flash('warning', $pesan);
            }
            break;
        case 'hapus':
            keranjang_set($idv, 0);
            break;
    }
    redirect('keranjang.php');
}

$isi = keranjang_detail();
$total = array_sum(array_column($isi, 'subtotal'));
$adaMasalah = (bool) array_filter($isi, fn($i) => $i['jumlah'] > $i['stok'] || !$i['aktif']);

toko_header('Keranjang', 'keranjang');
?>
<h1 class="h3 mb-4">Keranjang Belanja</h1>
<?php if (!$isi): ?>
  <div class="py-5 border-top border-bottom">
    <p class="mb-3">Keranjang masih kosong.</p>
    <a class="btn btn-hibiscus" href="index.php">Lihat katalog</a>
  </div>
<?php else: ?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card">
      <ul class="list-group list-group-flush">
        <?php foreach ($isi as $i): ?>
          <li class="list-group-item py-3">
            <div class="d-flex gap-3 align-items-center">
              <?= produk_media($i, 'sm') ?>
              <div class="flex-fill">
                <a class="fw-semibold text-reset" href="produk.php?id=<?= e($i['id_produk']) ?>"><?= e($i['nama_produk']) ?></a>
                <div class="small text-muted">Ukuran <?= e($i['ukuran']) ?> · <?= rupiah($i['harga_jual']) ?> · stok <?= e($i['stok']) ?></div>
                <div class="small text-danger js-pesan" role="status"><?= $i['jumlah'] > $i['stok'] ? 'Stok tidak cukup, kurangi jumlah.' : '' ?></div>
              </div>
              <form method="post" class="qty js-qty" data-harga="<?= e($i['harga_jual']) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="ubah"><input type="hidden" name="id_varian" value="<?= e($i['id_varian']) ?>">
                <button type="button" class="btn btn-sm btn-outline-secondary js-step" data-step="-1" aria-label="Kurangi satu">−</button>
                <input class="form-control form-control-sm text-center" type="number" min="1" max="<?= e($i['stok']) ?>" name="jumlah"
                       value="<?= e($i['jumlah']) ?>" inputmode="numeric" aria-label="Jumlah <?= e($i['nama_produk']) ?> ukuran <?= e($i['ukuran']) ?>">
                <button type="button" class="btn btn-sm btn-outline-secondary js-step" data-step="1" aria-label="Tambah satu">+</button>
                <noscript><button class="btn btn-sm btn-outline-secondary">Simpan</button></noscript>
              </form>
              <div class="fw-semibold text-end js-subtotal" style="min-width:110px"><?= rupiah($i['subtotal']) ?></div>
              <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="hapus"><input type="hidden" name="id_varian" value="<?= e($i['id_varian']) ?>">
                <button class="btn btn-sm btn-link text-danger" title="Hapus"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <span>Subtotal (<span class="js-pasang"><?= keranjang_jumlah() ?></span> pasang)</span><b class="js-total"><?= rupiah($total) ?></b>
        </div>
        <div class="small text-muted mb-3">Ongkir dihitung di halaman checkout sesuai kota tujuan.</div>
        <a class="btn btn-hibiscus w-100 js-checkout <?= $adaMasalah ? 'disabled' : '' ?>" href="checkout.php">Lanjut ke Checkout</a>
      </div>
    </div>
  </div>
</div>
<script>
/* Jumlah berubah -> harga langsung dihitung ulang di layar, lalu disimpan ke server
   di latar belakang. Server tetap memeriksa stok dan mengirim angka akhirnya. */
(function () {
  var rp = function (n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); };
  var forms = document.querySelectorAll('.js-qty');

  function hitungUlangLokal() {
    var total = 0, pasang = 0;
    forms.forEach(function (f) {
      var q = parseInt(f.jumlah.value, 10) || 0, sub = q * parseFloat(f.dataset.harga);
      f.closest('li').querySelector('.js-subtotal').textContent = rp(sub);
      total += sub; pasang += q;
    });
    document.querySelector('.js-total').textContent = rp(total);
    document.querySelector('.js-pasang').textContent = pasang;
    document.querySelectorAll('.cart-count').forEach(function (el) { el.textContent = '(' + pasang + ')'; });
  }

  function simpan(f) {
    clearTimeout(f._t);
    f._t = setTimeout(function () {
      fetch(location.pathname, { method: 'POST', body: new FormData(f), headers: { 'Accept': 'application/json' } })
        .then(function (r) { if (!r.ok) throw r; return r.json(); })
        .then(function (d) {
          f.jumlah.value = d.jumlah;
          f.closest('li').querySelector('.js-subtotal').textContent = d.subtotal;
          f.closest('li').querySelector('.js-pesan').textContent = d.pesan || f._pesan || '';
          document.querySelector('.js-total').textContent = d.total;
          document.querySelector('.js-pasang').textContent = d.pasang;
          document.querySelectorAll('.cart-count').forEach(function (el) { el.textContent = '(' + d.pasang + ')'; });
          document.querySelector('.js-checkout').classList.toggle('disabled', d.jumlah < 1);
        })
        .catch(function () { f.submit(); });   // jika gagal, kirim form biasa
    }, 350);
  }

  forms.forEach(function (f) {
    var input = f.jumlah, max = parseInt(input.max, 10) || 1;
    function atur(nilai) {
      var q = Math.min(Math.max(nilai, 1), max);
      f._pesan = nilai > max ? 'Stok ukuran ini tersedia hanya ' + max + ' pasang.' : '';
      f.closest('li').querySelector('.js-pesan').textContent = f._pesan;
      input.value = q;
      hitungUlangLokal();
      simpan(f);
    }
    input.addEventListener('input', function () { if (input.value !== '') atur(parseInt(input.value, 10) || 1); });
    input.addEventListener('blur', function () { if (input.value === '') atur(1); });
    f.querySelectorAll('.js-step').forEach(function (b) {
      b.addEventListener('click', function () { atur((parseInt(input.value, 10) || 1) + parseInt(b.dataset.step, 10)); });
    });
    f.addEventListener('submit', function (e) { e.preventDefault(); simpan(f); });
  });
})();
</script>
<?php endif; ?>
<?php toko_footer();
