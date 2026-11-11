<?php
require __DIR__ . '/../inc/admin.php';

if (admin()) {
    redirect('admin/index.php');
}

$error = '';
if (is_post()) {
    csrf_check();
    $username = post('username');
    $password = (string) ($_POST['password'] ?? '');
    $u = row('SELECT * FROM pengguna WHERE username = ? AND aktif = 1', [$username]);

    $ok = $u && cek_password($password, $u['password_hash'], function (string $baru) use ($u) {
        q('UPDATE pengguna SET password_hash = ? WHERE id_pengguna = ?', [$baru, $u['id_pengguna']]);
    });

    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['admin'] = [
            'id'       => (int) $u['id_pengguna'],
            'username' => $u['username'],
            'nama'     => $u['nama_lengkap'],
            'peran'    => $u['peran'],
        ];
        redirect('admin/index.php');
    }
    $error = 'Username atau password tidak cocok, atau akun sudah dinonaktifkan.';
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk Staf · Hibiscus Footwear</title>
  <?= head_assets() ?>
</head>
<body class="min-vh-100 d-flex flex-column">
  <header class="topbar py-3">
    <div class="container"><a class="wordmark" href="<?= url('index.php') ?>">Hibiscus<span>Footwear</span></a></div>
  </header>
  <main class="container flex-grow-1 d-flex align-items-center py-5">
    <div style="width: 100%; max-width: 360px">
      <div class="eyebrow mb-2">Panel staf</div>
      <h1 class="h2 mb-4">Masuk</h1>
      <?php if ($error): ?><div class="alert alert-danger py-2" role="alert"><div class="alert-title">Gagal masuk</div><div><span class="alert-reason">Alasan:</span> <?= e($error) ?></div></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label" for="username">Username</label>
          <input class="form-control" id="username" name="username" value="<?= e(post('username')) ?>" autocomplete="username" required autofocus>
        </div>
        <div class="mb-4">
          <label class="form-label" for="password">Password</label>
          <input class="form-control" id="password" type="password" name="password" autocomplete="current-password" required>
        </div>
        <button class="btn btn-hibiscus w-100">Masuk</button>
      </form>
      <p class="small text-muted mt-4 mb-0">Untuk admin, gudang, produksi, dan penjualan. Pelanggan masuk lewat <a href="<?= url('masuk.php') ?>">halaman toko</a>.</p>
    </div>
  </main>
</body>
</html>
