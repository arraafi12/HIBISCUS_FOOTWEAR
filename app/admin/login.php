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
    $error = 'Username atau password salah.';
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk Admin · <?= e(config('app_name')) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= url('assets/app.css') ?>">
</head>
<body class="d-flex align-items-center min-vh-100 bg-hibiscus">
  <div class="container" style="max-width: 400px">
    <div class="card shadow">
      <div class="card-body p-4">
        <div class="text-center mb-4">
          <i class="bi bi-flower2 display-5 text-hibiscus"></i>
          <h1 class="h5 mt-2 mb-0"><?= e(config('app_name')) ?></h1>
          <div class="text-muted small">Masuk sebagai admin / pegawai</div>
        </div>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="username">Username</label>
            <input class="form-control" id="username" name="username" value="<?= e(post('username')) ?>" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" id="password" type="password" name="password" required>
          </div>
          <button class="btn btn-hibiscus w-100">Masuk</button>
        </form>
        <div class="text-center mt-3"><a class="small" href="<?= url('index.php') ?>">&larr; Kembali ke toko</a></div>
      </div>
    </div>
  </div>
</body>
</html>
