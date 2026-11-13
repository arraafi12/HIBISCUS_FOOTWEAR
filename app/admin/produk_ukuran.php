<?php
require __DIR__ . '/../inc/admin.php';
require_admin();

$id = (int) get('id', post('id_produk'));
$produk = row('SELECT p.*, k.nama_kategori FROM produk p JOIN kategori k ON k.id_kategori = p.id_kategori WHERE p.id_produk = ?', [$id]);
if (!$produk) {
    flash('warning', 'Produk tidak ditemukan.');
    redirect('admin/produk.php');
}

if (is_post()) {
    csrf_check();
    try {
        switch (post('action')) {
            case 'tambah':
                $from = (int) post('ukuran_dari');
                $to   = (int) (post('ukuran_sampai') ?: $from);
                $min  = (int) post('stok_minimum', 3);
                db()->beginTransaction();
                for ($u = $from; $u <= $to; $u++) {
                    q('INSERT INTO produk_ukuran (id_produk, ukuran, sku, stok_minimum) VALUES (?, ?, ?, ?)',
                      [$id, $u, $produk['kode_produk'] . '-' . $u, $min]);
                }
                db()->commit();
                flash('success', 'Ukuran berhasil ditambahkan. Stok akan terisi dari Hasil Produksi.');
                break;
            case 'stok_minimum':
                q('UPDATE produk_ukuran SET stok_minimum = ? WHERE id_varian = ? AND id_produk = ?',
                  [(int) post('stok_minimum'), (int) post('id_varian'), $id]);
                flash('success', 'Stok minimum diperbarui.');
                break;
            case 'hapus':
                q('DELETE FROM produk_ukuran WHERE id_varian = ? AND id_produk = ?', [(int) post('id_varian'), $id]);
                flash('success', 'Ukuran dihapus.');
                break;
            case 'foto':
                simpan_foto_produk($id, $_FILES['foto'] ?? []);
                flash('success', 'Foto produk tersimpan dan langsung tampil di toko.');
                break;
            case 'hapus_foto':
                q('DELETE FROM produk_foto WHERE id_produk = ?', [$id]);
                flash('success', 'Foto dihapus. Toko kembali menampilkan kotak nama produk.');
                break;
        }
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('danger', pesan_error($e), [
            'tambah'       => 'Gagal menambah ukuran',
            'stok_minimum' => 'Gagal mengubah stok minimum',
            'hapus'        => 'Gagal menghapus ukuran',
            'foto'         => 'Gagal mengunggah foto',
            'hapus_foto'   => 'Gagal menghapus foto',
        ][post('action')] ?? null);
    }
    redirect('admin/produk_ukuran.php?id=' . $id);
}

$varian = rows('SELECT * FROM produk_ukuran WHERE id_produk = ? ORDER BY ukuran', [$id]);
$foto   = foto_siap() ? row('SELECT tipe_mime, ukuran_byte, lebar, tinggi, diunggah_pada, UNIX_TIMESTAMP(diunggah_pada) AS versi
                              FROM produk_foto WHERE id_produk = ?', [$id]) : null;
$produk['foto_versi'] = $foto['versi'] ?? null;

admin_header('Produk ' . $produk['nama_produk'], 'produk');
page_title('Foto, Ukuran & Stok: ' . $produk['nama_produk'],
    '<a class="btn btn-outline-primary me-2" href="produk.php?edit=' . $id . '"><i class="bi bi-pencil"></i> Ubah Nama / Data Produk</a>' .
    '<a class="btn btn-outline-secondary" href="produk.php">Daftar Produk</a>');
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-body">
        <p class="text-muted small mb-2">
          <?= e($produk['kode_produk']) ?> · <?= e($produk['nama_kategori']) ?> · <?= rupiah($produk['harga_jual']) ?>.
          Stok tidak diubah manual: bertambah dari <b>Hasil Produksi</b> dan retur kondisi baik, berkurang dari <b>Penjualan</b>.
        </p>
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead class="table-light"><tr><th>Ukuran</th><th>SKU</th><th class="num">Stok</th><th>Status</th><th>Stok Minimum</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($varian as $v): ?>
              <tr>
                <td class="fw-semibold"><?= e($v['ukuran']) ?></td>
                <td><?= e($v['sku']) ?></td>
                <td class="num"><?= angka($v['stok']) ?></td>
                <td><?= status_badge($v['stok'] == 0 ? 'HABIS' : ($v['stok'] <= $v['stok_minimum'] ? 'MENIPIS' : 'AMAN')) ?></td>
                <td>
                  <form method="post" class="d-flex gap-1" style="max-width: 160px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="stok_minimum">
                    <input type="hidden" name="id_varian" value="<?= e($v['id_varian']) ?>">
                    <input class="form-control form-control-sm" type="number" min="0" name="stok_minimum" value="<?= e($v['stok_minimum']) ?>">
                    <button class="btn btn-sm btn-outline-primary" title="Simpan"><i class="bi bi-check-lg"></i></button>
                  </form>
                </td>
                <td class="text-end"><?= delete_button('', ['action' => 'hapus', 'id_varian' => $v['id_varian']], 'Hapus ukuran ' . $v['ukuran'] . '?') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$varian): ?><tr><td colspan="6" class="text-center text-muted py-3">Belum ada ukuran.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header fw-semibold">Foto Produk</div>
      <div class="card-body">
        <div class="mb-3" style="max-width: 220px"><?= produk_media($produk) ?></div>
        <?php if (!foto_siap()): ?>
          <div class="alert alert-warning small mb-0" role="status">
            <div class="alert-title">Perhatian</div>
            Fitur foto belum aktif. Import file <code>database/09_foto_produk.sql</code> di phpMyAdmin terlebih dahulu.
          </div>
        <?php else: ?>
          <?php if ($foto): ?>
            <p class="small text-muted mb-2">
              <?= e(FOTO_TIPE[$foto['tipe_mime']] ?? $foto['tipe_mime']) ?> · <?= e($foto['lebar']) ?>×<?= e($foto['tinggi']) ?> px ·
              <?= number_format($foto['ukuran_byte'] / 1024, 0, ',', '.') ?> KB · diunggah <?= e(tgl($foto['diunggah_pada'], true)) ?>
            </p>
          <?php endif; ?>
          <form method="post" enctype="multipart/form-data" id="formFoto">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="foto">
            <label class="form-label" for="foto"><?= $foto ? 'Ganti foto' : 'Unggah foto' ?></label>
            <input class="form-control form-control-sm mb-1" type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" required>
            <div class="form-text mb-2" id="infoFoto">JPG, PNG, atau WebP. Foto besar otomatis dikecilkan (maks. 1200 px, <?= FOTO_MAKS_BYTE / 1024 ?> KB).</div>
            <button class="btn btn-sm btn-hibiscus w-100" id="kirimFoto">Simpan Foto</button>
          </form>
          <?php if ($foto): ?>
            <div class="mt-2"><?= delete_button('', ['action' => 'hapus_foto'], 'Hapus foto produk ini?', 'Hapus foto') ?></div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-header fw-semibold">Tambah Ukuran</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="tambah">
          <div class="row g-2 mb-2">
            <div class="col"><label class="form-label">Dari</label><input class="form-control" type="number" min="30" max="50" name="ukuran_dari" required></div>
            <div class="col"><label class="form-label">Sampai</label><input class="form-control" type="number" min="30" max="50" name="ukuran_sampai"></div>
          </div>
          <div class="mb-3"><label class="form-label">Stok minimum</label><input class="form-control" type="number" min="0" name="stok_minimum" value="3"></div>
          <button class="btn btn-hibiscus w-100">Tambah</button>
          <div class="form-text">Isi "Sampai" untuk menambah beberapa ukuran sekaligus. SKU dibuat otomatis.</div>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
/* Kecilkan foto di browser sebelum diunggah (maks. 1200 px, JPEG), karena
   server XAMPP tidak memiliki ekstensi GD dan batas paket MySQL 1 MB. */
(function () {
  var input = document.getElementById('foto');
  if (!input) return;
  var info = document.getElementById('infoFoto'), tombol = document.getElementById('kirimFoto');
  var MAKS_PX = 1200, MAKS_BYTE = <?= FOTO_MAKS_BYTE ?>, kb = function (b) { return Math.round(b / 1024) + ' KB'; };

  input.addEventListener('change', function () {
    var file = input.files[0];
    if (!file || !/^image\/(jpeg|png|webp)$/.test(file.type)) return;
    var img = new Image(), urlAsli = URL.createObjectURL(file);
    img.onload = function () {
      URL.revokeObjectURL(urlAsli);
      var skala = Math.min(1, MAKS_PX / Math.max(img.width, img.height));
      if (skala === 1 && file.size <= 600 * 1024) { info.textContent = 'Siap diunggah: ' + img.width + '×' + img.height + ' px, ' + kb(file.size) + '.'; return; }
      var c = document.createElement('canvas');
      c.width = Math.round(img.width * skala); c.height = Math.round(img.height * skala);
      var ctx = c.getContext('2d');
      ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);   // latar putih untuk PNG transparan
      ctx.drawImage(img, 0, 0, c.width, c.height);
      tombol.disabled = true; info.textContent = 'Mengecilkan foto…';
      c.toBlob(function (blob) {
        tombol.disabled = false;
        if (!blob) { info.textContent = 'Foto tidak bisa dikecilkan; file asli akan diunggah.'; return; }
        var dt = new DataTransfer();
        dt.items.add(new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }));
        input.files = dt.files;
        info.textContent = 'Dikecilkan: ' + kb(file.size) + ' → ' + kb(blob.size) + ' (' + c.width + '×' + c.height + ' px).'
          + (blob.size > MAKS_BYTE ? ' Masih melebihi batas, pilih foto lain.' : '');
      }, 'image/jpeg', 0.85);
    };
    img.src = urlAsli;
  });
})();
</script>
<?php admin_footer();
