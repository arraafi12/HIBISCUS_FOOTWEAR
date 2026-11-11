<?php
require __DIR__ . '/inc/toko.php';

if (pelanggan()) {
    redirect('akun.php');
}

if (is_post()) {
    csrf_check();
    $p = row('SELECT * FROM pelanggan WHERE email = ?', [post('email')]);
    $_SESSION['old_email'] = post('email');
    if (!akun_pelanggan_siap()) {
        flash('danger', 'Fitur akun pelanggan belum aktif karena kolom password belum ada di database. Import file database/08_akun_pelanggan.sql di phpMyAdmin, lalu coba lagi.', 'Gagal masuk');
        redirect('masuk.php');
    }
    if ($p && !$p['password_hash']) {
        flash('danger', 'Akun ini belum punya password untuk toko online. Minta admin mengisi password di menu Pelanggan.', 'Gagal masuk');
        redirect('masuk.php');
    }
    $ok = $p && cek_password((string) ($_POST['password'] ?? ''), $p['password_hash'], function (string $baru) use ($p) {
        q('UPDATE pelanggan SET password_hash = ? WHERE id_pelanggan = ?', [$baru, $p['id_pelanggan']]);
    });
    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['pelanggan'] = ['id' => (int) $p['id_pelanggan'], 'nama' => $p['nama_pelanggan'], 'email' => $p['email']];
        $tujuan = $_SESSION['setelah_login'] ?? '';
        unset($_SESSION['setelah_login']);
        flash('success', 'Selamat datang, ' . $p['nama_pelanggan'] . '!');
        if ($tujuan !== '' && str_starts_with($tujuan, BASE_URL . '/')) {
            header('Location: ' . $tujuan);
            exit;
        }
        redirect(keranjang() ? 'checkout.php' : 'index.php');
    }
    flash('danger', 'Email atau password tidak cocok dengan akun mana pun.', 'Gagal masuk');
    redirect('masuk.php');
}
$oldEmail = $_SESSION['old_email'] ?? '';
unset($_SESSION['old_email']);

toko_header('Masuk', 'masuk');
?>
<div class="mx-auto" style="max-width: 420px">
  <div class="card">
    <div class="card-body p-4">
      <h1 class="h3 mb-4">Masuk</h1>
      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="email">Email</label>
          <input class="form-control" type="email" id="email" name="email" value="<?= e($oldEmail) ?>" required autofocus></div>
        <div class="mb-3"><label class="form-label" for="password">Password</label>
          <input class="form-control" type="password" id="password" name="password" required></div>
        <button class="btn btn-hibiscus w-100">Masuk</button>
      </form>
      <p class="small text-center mt-3 mb-0">Belum punya akun? <a href="daftar.php">Daftar di sini</a></p>
    </div>
  </div>
</div>
<?php toko_footer();
