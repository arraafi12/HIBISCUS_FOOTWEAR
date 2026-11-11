<?php
require __DIR__ . '/../inc/admin.php';
require_admin();

$pelanggan = rows('SELECT id_pelanggan, nama_pelanggan, email, alamat, kota, kode_pos FROM pelanggan ORDER BY nama_pelanggan');
$varian = rows('SELECT pu.id_varian, pu.sku, pu.ukuran, pu.stok, p.nama_produk, p.harga_jual
                  FROM produk_ukuran pu JOIN produk p ON p.id_produk = pu.id_produk
                 WHERE p.aktif = 1 AND pu.stok > 0
                 ORDER BY p.nama_produk, pu.ukuran');

if (is_post()) {
    csrf_check();
    $items = [];
    foreach ($_POST['id_varian'] ?? [] as $i => $idv) {
        if ($idv !== '') {
            $items[(int) $idv] = ($items[(int) $idv] ?? 0) + (int) ($_POST['jumlah'][$i] ?? 0);
        }
    }
    $idp = (int) post('id_pelanggan');
    $p = row('SELECT * FROM pelanggan WHERE id_pelanggan = ?', [$idp]);
    try {
        if (!$p) {
            throw new InvalidArgumentException('Pilih pelanggan.');
        }
        $alamat = post('alamat_kirim') ?: trim($p['alamat'] . ', ' . $p['kota'] . ' ' . $p['kode_pos']);
        $ongkir = post('ongkir') === '' ? ongkir_untuk($p['kota']) : (int) post('ongkir');
        $id = buat_pesanan($idp, $items, $alamat, $ongkir, post('catatan') ?: null);
        flash('success', 'Pesanan dibuat dan stok sudah dipesan. Menunggu pembayaran.');
        redirect('admin/penjualan_detail.php?id=' . $id);
    } catch (Throwable $e) {
        flash('danger', pesan_error($e), 'Gagal membuat pesanan');
        $_SESSION['old'] = $_POST;
        redirect('admin/penjualan_tambah.php');
    }
}

$old = $_SESSION['old'] ?? [];
unset($_SESSION['old']);

$row = function ($idv = '', $jml = 1) use ($varian) {
    $h = '<tr><td><select class="form-select form-select-sm" name="id_varian[]"><option value="">-- pilih produk & ukuran --</option>';
    foreach ($varian as $v) {
        $h .= '<option value="' . e($v['id_varian']) . '"' . ((string) $idv === (string) $v['id_varian'] ? ' selected' : '') . '>'
            . e($v['nama_produk'] . ' · ukuran ' . $v['ukuran'] . ' · stok ' . $v['stok'] . ' · ' . rupiah($v['harga_jual'])) . '</option>';
    }
    return $h . '</select></td><td style="width:120px"><input class="form-control form-control-sm" type="number" min="1" name="jumlah[]" value="' . e($jml) . '"></td>'
        . '<td style="width:50px"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row><i class="bi bi-x-lg"></i></button></td></tr>';
};

admin_header('Pesanan Baru', 'penjualan');
page_title('Pesanan Baru', '<a class="btn btn-outline-secondary" href="penjualan.php">Kembali</a>');
?>
<form method="post" class="card">
  <div class="card-body">
    <?= csrf_field() ?>
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label">Pelanggan <span class="text-danger">*</span></label>
        <select class="form-select" name="id_pelanggan" required>
          <option value="">-- pilih pelanggan --</option>
          <?php foreach ($pelanggan as $p): ?>
            <option value="<?= e($p['id_pelanggan']) ?>" <?= (string) ($old['id_pelanggan'] ?? '') === (string) $p['id_pelanggan'] ? 'selected' : '' ?>>
              <?= e($p['nama_pelanggan'] . ' · ' . $p['kota'] . ' · ' . $p['email']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Ongkir (Rp)</label>
        <input class="form-control" type="number" min="0" name="ongkir" value="<?= e($old['ongkir'] ?? '') ?>" placeholder="otomatis sesuai kota">
      </div>
      <div class="col-md-3">
        <label class="form-label">Catatan</label>
        <input class="form-control" name="catatan" value="<?= e($old['catatan'] ?? '') ?>">
      </div>
      <div class="col-12">
        <label class="form-label">Alamat Kirim</label>
        <input class="form-control" name="alamat_kirim" value="<?= e($old['alamat_kirim'] ?? '') ?>" placeholder="kosongkan untuk memakai alamat pelanggan">
      </div>
    </div>
    <table class="table table-sm" id="tblItem">
      <thead class="table-light"><tr><th>Produk & Ukuran</th><th>Jumlah</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($old['id_varian'] ?? [''] as $i => $idv) echo $row($idv, $old['jumlah'][$i] ?? 1); ?>
      </tbody>
      <template><?= $row() ?></template>
    </table>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-add-row="#tblItem">Tambah baris</button>
  </div>
  <div class="card-footer">
    <button class="btn btn-hibiscus">Buat Pesanan</button>
    <span class="small text-muted ms-2">Hanya ukuran yang masih ada stoknya yang ditampilkan. Harga diambil dari harga produk saat ini.</span>
  </div>
</form>
<?php admin_footer();
