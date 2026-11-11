<?php
require __DIR__ . '/inc/toko.php';

if (pelanggan()) {
    redirect('akun.php');
}

$fields = ['nama_pelanggan', 'email', 'no_hp', 'alamat', 'kota', 'kode_pos'];
if (is_post()) {
    csrf_check();
    $d = [];
    foreach ($fields as $f) {
        $d[$f] = post($f);
    }
    $pw  = (string) ($_POST['password'] ?? '');
    $err = [];
    foreach (['nama_pelanggan' => 'Nama', 'email' => 'Email', 'no_hp' => 'No. HP', 'alamat' => 'Alamat', 'kota' => 'Kota'] as $f => $l) {
        if ($d[$f] === '') {
            $err[] = "$l wajib diisi.";
        }
    }
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        $err[] = 'Email tidak valid.';
    }
    if (strlen($pw) < 6) {
        $err[] = 'Password minimal 6 karakter.';
    }
    if ($pw !== ($_POST['password2'] ?? '')) {
        $err[] = 'Konfirmasi password tidak sama.';
    }
    if (!$err) {
        try {
            q('INSERT INTO pelanggan (nama_pelanggan, email, password_hash, no_hp, alamat, kota, kode_pos) VALUES (?, ?, ?, ?, ?, ?, ?)',
              [$d['nama_pelanggan'], $d['email'], password_hash($pw, PASSWORD_DEFAULT), $d['no_hp'], $d['alamat'], $d['kota'], $d['kode_pos'] ?: null]);
            session_regenerate_id(true);
            $_SESSION['pelanggan'] = ['id' => (int) db()->lastInsertId(), 'nama' => $d['nama_pelanggan'], 'email' => $d['email']];
            flash('success', 'Akun berhasil dibuat. Selamat berbelanja!');
            redirect(keranjang() ? 'checkout.php' : 'index.php');
        } catch (PDOException $e) {
            $err[] = ($e->errorInfo[1] ?? 0) == 1062 ? 'Email sudah terdaftar. Silakan masuk.' : db_error($e);
        }
    }
    foreach ($err as $m) {
        flash('danger', $m);
    }
    $_SESSION['old'] = $d;
    redirect('daftar.php');
}
$old = $_SESSION['old'] ?? [];
unset($_SESSION['old']);

toko_header('Daftar');
?>
<div class="mx-auto" style="max-width: 560px">
  <div class="card">
    <div class="card-body p-4">
      <h1 class="h4 mb-3">Buat Akun</h1>
      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">Nama lengkap</label><input class="form-control" name="nama_pelanggan" value="<?= e($old['nama_pelanggan'] ?? '') ?>" required></div>
        <div class="row g-2 mb-3">
          <div class="col-sm-7"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required></div>
          <div class="col-sm-5"><label class="form-label">No. HP</label><input class="form-control" name="no_hp" value="<?= e($old['no_hp'] ?? '') ?>" required></div>
        </div>
        <div class="mb-3"><label class="form-label">Alamat</label><textarea class="form-control" name="alamat" rows="2" required><?= e($old['alamat'] ?? '') ?></textarea></div>
        <div class="row g-2 mb-3">
          <div class="col-8"><label class="form-label">Kota</label><input class="form-control" name="kota" value="<?= e($old['kota'] ?? '') ?>" required></div>
          <div class="col-4"><label class="form-label">Kode pos</label><input class="form-control" name="kode_pos" value="<?= e($old['kode_pos'] ?? '') ?>"></div>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-sm-6"><label class="form-label">Password</label><input class="form-control" type="password" name="password" minlength="6" required autocomplete="new-password"></div>
          <div class="col-sm-6"><label class="form-label">Ulangi password</label><input class="form-control" type="password" name="password2" minlength="6" required autocomplete="new-password"></div>
        </div>
        <button class="btn btn-hibiscus w-100">Daftar</button>
      </form>
      <p class="small text-center mt-3 mb-0">Sudah punya akun? <a href="masuk.php">Masuk</a></p>
    </div>
  </div>
</div>
<?php toko_footer();
