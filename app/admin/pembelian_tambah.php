<?php
require __DIR__ . '/../inc/admin.php';
$u = require_admin();

$supplier = options('SELECT id_supplier, nama_supplier FROM supplier WHERE aktif = 1 ORDER BY nama_supplier');
$bahan    = rows('SELECT id_bahan, kode_bahan, nama_bahan, satuan, harga_terakhir FROM bahan_baku ORDER BY nama_bahan');

if (is_post()) {
    csrf_check();
    $items = [];
    $ids   = $_POST['id_bahan'] ?? [];
    foreach ($ids as $i => $idb) {
        if ($idb === '') {
            continue;
        }
        $jml   = (float) ($_POST['jumlah'][$i] ?? 0);
        $harga = (float) ($_POST['harga'][$i] ?? 0);
        if (isset($items[$idb])) {
            flash('danger', 'Bahan yang sama diinput dua kali dalam satu faktur. Gabungkan jumlahnya dalam satu baris.', 'Gagal menyimpan pembelian');
            $items = null;
            break;
        }
        $items[$idb] = [$jml, $harga];
    }

    if ($items === []) {
        flash('danger', 'Belum ada bahan baku yang diisi. Isi minimal satu baris.', 'Gagal menyimpan pembelian');
    } elseif ($items !== null) {
        try {
            db()->beginTransaction();
            q('INSERT INTO pembelian (no_faktur, tanggal, id_supplier, id_pengguna, keterangan) VALUES (?, ?, ?, ?, ?)',
              [post('no_faktur'), post('tanggal'), (int) post('id_supplier'), $u['id'], post('keterangan') ?: null]);
            $id = (int) db()->lastInsertId();
            foreach ($items as $idb => [$jml, $harga]) {
                q('INSERT INTO detail_pembelian (id_pembelian, id_bahan, jumlah, harga_satuan) VALUES (?, ?, ?, ?)',
                  [$id, (int) $idb, $jml, $harga]);
            }
            db()->commit();
            flash('success', 'Pembelian tersimpan. Stok bahan baku sudah bertambah.');
            redirect('admin/pembelian_detail.php?id=' . $id);
        } catch (PDOException $e) {
            db()->rollBack();
            flash('danger', db_error($e), 'Gagal menyimpan pembelian');
        }
    }
    $_SESSION['old'] = $_POST;
    redirect('admin/pembelian_tambah.php');
}

$old = $_SESSION['old'] ?? [];
unset($_SESSION['old']);

$bahanOptions = function ($selected = '') use ($bahan) {
    $h = '<option value="">-- pilih bahan --</option>';
    foreach ($bahan as $b) {
        $h .= '<option value="' . e($b['id_bahan']) . '"' . ((string) $selected === (string) $b['id_bahan'] ? ' selected' : '') . '>'
            . e($b['nama_bahan'] . ' (' . $b['satuan'] . ')' . ($b['harga_terakhir'] ? ' · terakhir ' . rupiah($b['harga_terakhir']) : ''))
            . '</option>';
    }
    return $h;
};
$row = function ($idb = '', $jml = '', $harga = '') use ($bahanOptions) {
    return '<tr><td><select class="form-select form-select-sm" name="id_bahan[]">' . $bahanOptions($idb) . '</select></td>'
        . '<td><input class="form-control form-control-sm" type="number" step="0.01" min="0.01" name="jumlah[]" value="' . e($jml) . '"></td>'
        . '<td><input class="form-control form-control-sm" type="number" step="1" min="0" name="harga[]" value="' . e($harga) . '"></td>'
        . '<td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row><i class="bi bi-x-lg"></i></button></td></tr>';
};

admin_header('Pembelian Baru', 'pembelian');
page_title('Pembelian Baru', '<a class="btn btn-outline-secondary" href="pembelian.php">Kembali</a>');
?>
<form method="post" class="card">
  <div class="card-body">
    <?= csrf_field() ?>
    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <label class="form-label">No. Faktur <span class="text-danger">*</span></label>
        <input class="form-control" name="no_faktur" required value="<?= e($old['no_faktur'] ?? next_number('pembelian', 'no_faktur', 'PB-' . date('Y-m') . '-')) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Tanggal <span class="text-danger">*</span></label>
        <input class="form-control" type="date" name="tanggal" required value="<?= e($old['tanggal'] ?? date('Y-m-d')) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Supplier <span class="text-danger">*</span></label>
        <select class="form-select" name="id_supplier" required>
          <option value="">-- pilih supplier --</option>
          <?php foreach ($supplier as $v => $t): ?>
            <option value="<?= e($v) ?>" <?= (string) ($old['id_supplier'] ?? '') === (string) $v ? 'selected' : '' ?>><?= e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label">Keterangan</label>
        <input class="form-control" name="keterangan" value="<?= e($old['keterangan'] ?? '') ?>">
      </div>
    </div>

    <table class="table table-sm" id="tblItem">
      <thead class="table-light"><tr><th style="width:50%">Bahan Baku</th><th>Jumlah</th><th>Harga Satuan (Rp)</th><th></th></tr></thead>
      <tbody>
        <?php
        $oldIds = $old['id_bahan'] ?? [''];
        foreach ($oldIds as $i => $idb) {
            echo $row($idb, $old['jumlah'][$i] ?? '', $old['harga'][$i] ?? '');
        }
        ?>
      </tbody>
      <template><?= $row() ?></template>
    </table>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-add-row="#tblItem">Tambah baris</button>
  </div>
  <div class="card-footer">
    <button class="btn btn-hibiscus">Simpan Pembelian</button>
    <span class="small text-muted ms-2">Total faktur dan stok bahan dihitung otomatis oleh database.</span>
  </div>
</form>
<?php admin_footer();
