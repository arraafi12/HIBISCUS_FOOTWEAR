<?php
require __DIR__ . '/inc/toko.php';

if (is_post()) {
    csrf_check();

    // Hapus satu item varian
    if (!empty($_POST['hapus_varian'])) {
        keranjang_set((int) $_POST['hapus_varian'], 0);
        redirect('keranjang.php');
    }

    $act = post('action');

    // Lanjut ke Checkout dengan item terpilih
    if ($act === 'checkout') {
        $pilih = array_map('intval', $_POST['pilih'] ?? []);
        if (empty($pilih)) {
            flash('warning', 'Pilih minimal 1 produk untuk checkout.');
            redirect('keranjang.php');
        }
        $_SESSION['pilihan_keranjang'] = $pilih;
        redirect('checkout.php');
    }

    // Simpan pilihan checkbox otomatis di latar belakang
    if ($act === 'simpan_pilihan') {
        $_SESSION['pilihan_keranjang'] = array_map('intval', $_POST['pilih'] ?? []);
        if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        redirect('keranjang.php');
    }

    // Ubah jumlah item
    if ($act === 'ubah') {
        $idv  = (int) post('id_varian');
        $stok = (int) val('SELECT pu.stok FROM produk_ukuran pu JOIN produk p ON p.id_produk = pu.id_produk WHERE pu.id_varian = ? AND p.aktif = 1', [$idv]);
        $jml  = max(1, (int) post('jumlah'));
        $pesan = '';
        if ($jml > $stok) {
            $jml   = max(0, $stok);
            $pesan = "Stok ukuran ini tersedia hanya $stok pasang, jumlah disesuaikan.";
        }
        keranjang_set($idv, $jml);

        if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') {
            $isi   = keranjang_detail();
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
        redirect('keranjang.php');
    }

    // Tambah item ke keranjang
    if ($act === 'tambah') {
        $idv  = (int) post('id_varian');
        $stok = (int) val('SELECT pu.stok FROM produk_ukuran pu JOIN produk p ON p.id_produk = pu.id_produk WHERE pu.id_varian = ? AND p.aktif = 1', [$idv]);
        $jml  = (keranjang()[$idv] ?? 0) + max(1, (int) post('jumlah', 1));
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
        redirect('keranjang.php');
    }

    redirect('keranjang.php');
}

$isi = keranjang_detail();
$total = array_sum(array_column($isi, 'subtotal'));
$adaMasalah = (bool) array_filter($isi, fn($i) => $i['jumlah'] > $i['stok'] || !$i['aktif']);

// Pastikan pilihan varian sinkron dan default SEMUA terpilih
$semuaId = array_column($isi, 'id_varian');
if (empty($_SESSION['pilihan_keranjang'])) {
    $_SESSION['pilihan_keranjang'] = $semuaId;
} else {
    $_SESSION['pilihan_keranjang'] = array_values(array_intersect($_SESSION['pilihan_keranjang'], $semuaId));
    if (empty($_SESSION['pilihan_keranjang']) && !empty($semuaId)) {
        $_SESSION['pilihan_keranjang'] = $semuaId;
    }
}
$dipilih = $_SESSION['pilihan_keranjang'];

// Hitung ringkasan item terpilih langsung di server
$dipilihDetail = array_filter($isi, fn($i) => in_array((int)$i['id_varian'], $dipilih, true));
$totalDipilihAwal  = array_sum(array_column($dipilihDetail, 'subtotal'));
$pasangDipilihAwal = array_sum(array_column($dipilihDetail, 'jumlah'));

toko_header('Keranjang', 'keranjang');
?>
<h1 class="h3 mb-4">Keranjang Belanja</h1>
<?php if (!$isi): ?>
  <div class="py-5 border-top border-bottom">
    <p class="mb-3">Keranjang masih kosong.</p>
    <a class="btn btn-hibiscus" href="index.php">Lihat katalog</a>
  </div>
<?php else: ?>
<form method="post" id="formKeranjang">
  <?= csrf_field() ?>
  <input type="hidden" name="action" id="actionForm" value="checkout">

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card">
        <div class="list-group-item py-2 px-3 border-bottom d-flex align-items-center gap-2 bg-light">
          <input class="form-check-input" type="checkbox" id="pilihSemua" title="Pilih semua"
                 <?= count($dipilih) === count($isi) && count($isi) > 0 ? 'checked' : '' ?>>
          <label class="form-check-label small text-muted fw-semibold" for="pilihSemua">Pilih Semua</label>
        </div>
        <ul class="list-group list-group-flush">
          <?php foreach ($isi as $i): $checked = in_array((int)$i['id_varian'], $dipilih, true); ?>
            <li class="list-group-item py-3 js-item" data-harga="<?= e($i['harga_jual']) ?>" data-varian="<?= e($i['id_varian']) ?>">
              <div class="d-flex gap-3 align-items-center">
                <input class="form-check-input js-ceklis flex-shrink-0" type="checkbox" name="pilih[]"
                       value="<?= e($i['id_varian']) ?>" <?= $checked ? 'checked' : '' ?>
                       aria-label="Pilih <?= e($i['nama_produk']) ?>">
                <?= produk_media($i, 'sm') ?>
                <div class="flex-fill">
                  <a class="fw-semibold text-reset" href="produk.php?id=<?= e($i['id_produk']) ?>"><?= e($i['nama_produk']) ?></a>
                  <div class="small text-muted">Ukuran <?= e($i['ukuran']) ?> · <?= rupiah($i['harga_jual']) ?> · stok <?= e($i['stok']) ?></div>
                  <div class="small text-danger js-pesan" role="status"><?= $i['jumlah'] > $i['stok'] ? 'Stok tidak cukup, kurangi jumlah.' : '' ?></div>
                </div>

                <!-- Stepper jumlah (tanpa tag form bertingkat) -->
                <div class="d-flex align-items-center gap-1 js-qty-wrapper" data-harga="<?= e($i['harga_jual']) ?>" data-varian="<?= e($i['id_varian']) ?>">
                  <button type="button" class="btn btn-sm btn-outline-secondary js-step" data-step="-1" aria-label="Kurangi satu">−</button>
                  <input class="form-control form-control-sm text-center js-input-jumlah" type="number" min="1" max="<?= e($i['stok']) ?>"
                         value="<?= e($i['jumlah']) ?>" inputmode="numeric" style="width: 55px" aria-label="Jumlah <?= e($i['nama_produk']) ?> ukuran <?= e($i['ukuran']) ?>">
                  <button type="button" class="btn btn-sm btn-outline-secondary js-step" data-step="1" aria-label="Tambah satu">+</button>
                </div>

                <div class="fw-semibold text-end js-subtotal" style="min-width:110px"><?= rupiah($i['subtotal']) ?></div>

                <!-- Tombol hapus -->
                <button type="submit" name="hapus_varian" value="<?= e($i['id_varian']) ?>"
                        class="btn btn-sm btn-link text-danger" title="Hapus"><i class="bi bi-trash"></i></button>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between mb-1">
            <span>Dipilih (<span class="js-pasang-dipilih"><?= $pasangDipilihAwal ?></span> pasang)</span>
            <b class="js-total-dipilih"><?= rupiah($totalDipilihAwal) ?></b>
          </div>
          <div class="d-flex justify-content-between mb-2 text-muted small">
            <span>Total keranjang (<span class="js-pasang"><?= keranjang_jumlah() ?></span> pasang)</span>
            <span class="js-total"><?= rupiah($total) ?></span>
          </div>
          <div class="small text-muted mb-3">Ongkir dihitung di halaman checkout sesuai kota tujuan.</div>
          <button type="submit" class="btn btn-hibiscus w-100 js-btn-checkout <?= ($adaMasalah || $pasangDipilihAwal === 0) ? 'disabled' : '' ?>"
                  onclick="document.getElementById('actionForm').value='checkout'" id="btnCheckout"
                  <?= $pasangDipilihAwal === 0 ? 'disabled' : '' ?>>
            Lanjut ke Checkout
          </button>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
(function () {
  var rp = function (n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); };
  var csrfToken = document.querySelector('input[name="_csrf"]').value;
  var ceklisAll = document.getElementById('pilihSemua');
  var ceklisItems = document.querySelectorAll('.js-ceklis');
  var btnCheckout = document.getElementById('btnCheckout');

  function hitungDipilih() {
    var totalDipilih = 0, pasangDipilih = 0;
    document.querySelectorAll('.js-item').forEach(function (li) {
      var cb = li.querySelector('.js-ceklis');
      if (cb && cb.checked) {
        var inputJml = li.querySelector('.js-input-jumlah');
        var q = inputJml ? (parseInt(inputJml.value, 10) || 0) : 0;
        var harga = parseFloat(li.dataset.harga) || 0;
        totalDipilih += q * harga;
        pasangDipilih += q;
      }
    });

    var elDipilih = document.querySelector('.js-pasang-dipilih');
    var elTotalDipilih = document.querySelector('.js-total-dipilih');
    if (elDipilih) elDipilih.textContent = pasangDipilih;
    if (elTotalDipilih) elTotalDipilih.textContent = rp(totalDipilih);

    // Update status checkbox master
    var checkedCount = Array.from(ceklisItems).filter(function(c){ return c.checked; }).length;
    if (ceklisAll) {
      ceklisAll.checked = checkedCount === ceklisItems.length && ceklisItems.length > 0;
      ceklisAll.indeterminate = checkedCount > 0 && checkedCount < ceklisItems.length;
    }

    // Aktifkan / nonaktifkan tombol checkout
    if (btnCheckout) {
      btnCheckout.disabled = (pasangDipilih === 0);
      btnCheckout.classList.toggle('disabled', pasangDipilih === 0);
    }
  }

  function hitungTotalSemua() {
    var total = 0, pasang = 0;
    document.querySelectorAll('.js-item').forEach(function (li) {
      var inputJml = li.querySelector('.js-input-jumlah');
      var q = inputJml ? (parseInt(inputJml.value, 10) || 0) : 0;
      var harga = parseFloat(li.dataset.harga) || 0;
      var sub = q * harga;
      var elSub = li.querySelector('.js-subtotal');
      if (elSub) elSub.textContent = rp(sub);
      total += sub;
      pasang += q;
    });
    document.querySelector('.js-total').textContent = rp(total);
    document.querySelector('.js-pasang').textContent = pasang;
    document.querySelectorAll('.cart-count').forEach(function (el) { el.textContent = '(' + pasang + ')'; });
    hitungDipilih();
  }

  function simpanJumlah(idVarian, jumlah, wrapper) {
    clearTimeout(wrapper._t);
    wrapper._t = setTimeout(function () {
      var fd = new FormData();
      fd.append('action', 'ubah');
      fd.append('id_varian', idVarian);
      fd.append('jumlah', jumlah);
      fd.append('_csrf', csrfToken);

      fetch(location.pathname, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
        .then(function (r) { if (!r.ok) throw r; return r.json(); })
        .then(function (d) {
          var input = wrapper.querySelector('.js-input-jumlah');
          if (input) input.value = d.jumlah;
          var li = wrapper.closest('li');
          if (li) {
            var elSub = li.querySelector('.js-subtotal');
            if (elSub) elSub.textContent = d.subtotal;
            var elPesan = li.querySelector('.js-pesan');
            if (elPesan) elPesan.textContent = d.pesan || '';
          }
          document.querySelector('.js-total').textContent = d.total;
          document.querySelector('.js-pasang').textContent = d.pasang;
          document.querySelectorAll('.cart-count').forEach(function (el) { el.textContent = '(' + d.pasang + ')'; });
          hitungDipilih();
        })
        .catch(function () {
          // Fallback reload jika ajax bermasalah
          location.reload();
        });
    }, 350);
  }

  // Stepper jumlah (+ / -) dan input
  document.querySelectorAll('.js-qty-wrapper').forEach(function (wrapper) {
    var input = wrapper.querySelector('.js-input-jumlah');
    var idVarian = wrapper.dataset.varian;
    var max = parseInt(input.max, 10) || 1;

    function ubahNilai(val) {
      var q = Math.min(Math.max(val, 1), max);
      var li = wrapper.closest('li');
      var elPesan = li ? li.querySelector('.js-pesan') : null;
      if (elPesan) {
        elPesan.textContent = val > max ? 'Stok ukuran ini tersedia hanya ' + max + ' pasang.' : '';
      }
      input.value = q;
      hitungTotalSemua();
      simpanJumlah(idVarian, q, wrapper);
    }

    input.addEventListener('input', function () {
      if (input.value !== '') ubahNilai(parseInt(input.value, 10) || 1);
    });
    input.addEventListener('blur', function () {
      if (input.value === '') ubahNilai(1);
    });

    wrapper.querySelectorAll('.js-step').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var cur = parseInt(input.value, 10) || 1;
        ubahNilai(cur + parseInt(btn.dataset.step, 10));
      });
    });
  });

  var _tPilih;
  function simpanPilihanKeSesi() {
    clearTimeout(_tPilih);
    _tPilih = setTimeout(function () {
      var fd = new FormData();
      fd.append('action', 'simpan_pilihan');
      fd.append('_csrf', csrfToken);
      ceklisItems.forEach(function (c) {
        if (c.checked) fd.append('pilih[]', c.value);
      });
      fetch(location.pathname, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
    }, 300);
  }

  // Event listener checkbox produk
  ceklisItems.forEach(function (cb) {
    cb.addEventListener('change', function () {
      hitungDipilih();
      simpanPilihanKeSesi();
    });
  });

  // Event listener checkbox Pilih Semua
  if (ceklisAll) {
    ceklisAll.addEventListener('change', function () {
      ceklisItems.forEach(function (cb) { cb.checked = ceklisAll.checked; });
      hitungDipilih();
      simpanPilihanKeSesi();
    });
  }

  // Inisialisasi hitungan awal
  hitungDipilih();
})();
</script>
<?php endif; ?>
<?php toko_footer();
