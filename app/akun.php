<?php
require __DIR__ . '/inc/toko.php';
$me = require_pelanggan();

if (is_post()) {
    csrf_check();
    $d = ['nama_pelanggan' => post('nama_pelanggan'), 'no_hp' => post('no_hp'), 'alamat' => post('alamat'),
          'kota' => post('kota'), 'kode_pos' => post('kode_pos')];
    if (in_array('', [$d['nama_pelanggan'], $d['no_hp'], $d['alamat'], $d['kota']], true)) {
        flash('danger', 'Nama, No. HP, alamat, dan kota wajib diisi.');
        redirect('akun.php#profil');
    }
    $pw = (string) ($_POST['password'] ?? '');
    if ($pw !== '' && strlen($pw) < 6) {
        flash('danger', 'Password baru minimal 6 karakter.');
        redirect('akun.php#profil');
    }
    q('UPDATE pelanggan SET nama_pelanggan = ?, no_hp = ?, alamat = ?, kota = ?, kode_pos = ? WHERE id_pelanggan = ?',
      [$d['nama_pelanggan'], $d['no_hp'], $d['alamat'], $d['kota'], $d['kode_pos'] ?: null, $me['id']]);
    if ($pw !== '') {
        q('UPDATE pelanggan SET password_hash = ? WHERE id_pelanggan = ?', [password_hash($pw, PASSWORD_DEFAULT), $me['id']]);
    }
    $_SESSION['pelanggan']['nama'] = $d['nama_pelanggan'];
    flash('success', 'Profil diperbarui.');
    redirect('akun.php#profil');
}

$pl = row('SELECT * FROM pelanggan WHERE id_pelanggan = ?', [$me['id']]);
$pesanan = rows('SELECT pj.*, (SELECT SUM(jumlah) FROM detail_penjualan d WHERE d.id_penjualan = pj.id_penjualan) AS pasang
                   FROM penjualan pj WHERE pj.id_pelanggan = ? ORDER BY pj.tanggal_pesan DESC', [$me['id']]);

toko_header('Akun Saya');
?>
<div class="row g-4">
  <div class="col-lg-8">
    <h1 class="h4 mb-3">Pesanan Saya</h1>
    <?php if (!$pesanan): ?>
      <div class="card card-body text-center text-muted py-5">Belum ada pesanan. <a href="index.php">Mulai belanja</a></div>
    <?php endif; ?>
    <?php foreach ($pesanan as $p): ?>
      <a class="card mb-2 text-reset text-decoration-none product-card" href="pesanan.php?id=<?= e($p['id_penjualan']) ?>">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div>
            <div class="fw-semibold"><?= e($p['no_pesanan']) ?></div>
            <div class="small text-muted"><?= e(tgl($p['tanggal_pesan'], true)) ?> · <?= angka($p['pasang']) ?> pasang</div>
          </div>
          <div class="text-end">
            <?= status_badge($p['status']) ?>
            <div class="fw-bold mt-1"><?= rupiah($p['total']) ?></div>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="col-lg-4" id="profil">
    <div class="card">
      <div class="card-header bg-white fw-semibold">Profil</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-2"><label class="form-label small">Email</label><input class="form-control form-control-sm" value="<?= e($pl['email']) ?>" disabled></div>
          <div class="mb-2"><label class="form-label small">Nama</label><input class="form-control form-control-sm" name="nama_pelanggan" value="<?= e($pl['nama_pelanggan']) ?>" required></div>
          <div class="mb-2"><label class="form-label small">No. HP</label><input class="form-control form-control-sm" name="no_hp" value="<?= e($pl['no_hp']) ?>" required></div>
          <div class="mb-2"><label class="form-label small">Alamat</label><textarea class="form-control form-control-sm" name="alamat" rows="2" required><?= e($pl['alamat']) ?></textarea></div>
          <div class="row g-2 mb-2">
            <div class="col-8"><label class="form-label small">Kota</label><input class="form-control form-control-sm" name="kota" value="<?= e($pl['kota']) ?>" required></div>
            <div class="col-4"><label class="form-label small">Kode pos</label><input class="form-control form-control-sm" name="kode_pos" value="<?= e($pl['kode_pos']) ?>"></div>
          </div>
          <div class="mb-3"><label class="form-label small">Password baru</label>
            <input class="form-control form-control-sm" type="password" name="password" autocomplete="new-password" placeholder="kosongkan jika tidak diganti"></div>
          <button class="btn btn-sm btn-hibiscus w-100">Simpan Profil</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php toko_footer();
