<?php
/**
 * Dimuat oleh setiap halaman: sesi, koneksi database, dan fungsi bantu.
 */
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

$GLOBALS['CONFIG'] = require __DIR__ . '/../config.php';

// ---------------------------------------------------------------------
//  URL dasar aplikasi (berfungsi di http://localhost/hibiscus maupun
//  di server bawaan PHP)
// ---------------------------------------------------------------------
(function () {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if (basename($dir) === 'admin') {
        $dir = str_replace('\\', '/', dirname($dir));
    }
    define('BASE_URL', rtrim($dir, '/'));
})();

function config(string $key)
{
    return $GLOBALS['CONFIG'][$key] ?? null;
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

// ---------------------------------------------------------------------
//  Database
// ---------------------------------------------------------------------
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo '<h2>Tidak bisa terhubung ke database</h2>';
            echo '<p>Pastikan MySQL di XAMPP sudah berjalan dan database <b>'
                . htmlspecialchars($c['name']) . '</b> sudah di-import.</p>';
            echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
            exit;
        }
    }
    return $pdo;
}

/** Jalankan query dan kembalikan PDOStatement. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/** Panggil stored procedure dan kembalikan semua result set. */
function call_proc(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $sets = [];
    do {
        if ($st->columnCount() > 0) {
            $sets[] = $st->fetchAll();
        }
    } while ($st->nextRowset());
    $st->closeCursor();
    return $sets;
}

/**
 * Ubah error database menjadi pesan yang mudah dipahami.
 * Pesan dari trigger (SIGNAL SQLSTATE '45000') ditampilkan apa adanya.
 */
function db_error(PDOException $e): string
{
    $info  = $e->errorInfo ?? [];
    $state = $info[0] ?? '';
    $code  = (int) ($info[1] ?? 0);
    $msg   = $info[2] ?? $e->getMessage();

    if ($state === '45000') {
        return $msg;
    }
    if ($code === 1062) {
        return preg_match("/Duplicate entry '([^']*)'/", $msg, $m)
            ? "Data \"{$m[1]}\" sudah ada (tidak boleh duplikat)."
            : 'Data sudah ada (tidak boleh duplikat).';
    }
    if ($code === 1451) {
        return 'Data tidak bisa dihapus/diubah karena masih dipakai di data lain.';
    }
    if ($code === 1452) {
        return 'Data referensi tidak ditemukan.';
    }
    if ($code === 4025 || $code === 3819) {
        return 'Nilai tidak valid (melanggar aturan data): ' . $msg;
    }
    return 'Terjadi kesalahan database: ' . $msg;
}

/** Nomor dokumen berikutnya, contoh next_number('produksi','no_produksi','PR-2026-11-') */
function next_number(string $table, string $column, string $prefix, int $digits = 3): string
{
    $last = val("SELECT $column FROM $table WHERE $column LIKE ? ORDER BY $column DESC LIMIT 1", [$prefix . '%']);
    $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
    return $prefix . str_pad((string) $n, $digits, '0', STR_PAD_LEFT);
}

// ---------------------------------------------------------------------
//  Request, keamanan, dan pesan
// ---------------------------------------------------------------------
function e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, $default = '')
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function get(string $key, $default = '')
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function redirect(string $path): void
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

/** Wajib dipanggil di awal setiap pemrosesan POST. */
function csrf_check(): void
{
    if (!hash_equals(csrf_token(), (string) ($_POST['_csrf'] ?? ''))) {
        http_response_code(400);
        exit('Sesi formulir kedaluwarsa. Silakan muat ulang halaman.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function render_flash(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as [$type, $msg]) {
        $html .= '<div class="alert alert-' . e($type) . ' alert-dismissible fade show" role="alert">'
            . e($msg) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

// ---------------------------------------------------------------------
//  Format tampilan
// ---------------------------------------------------------------------
function rupiah($n): string
{
    return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

function angka($n): string
{
    $f = (float) $n;
    return number_format($f, floor($f) == $f ? 0 : 2, ',', '.');
}

function tgl(?string $d, bool $jam = false): string
{
    if (!$d) {
        return '-';
    }
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($d);
    $s = date('j', $t) . ' ' . $bulan[(int) date('n', $t)] . ' ' . date('Y', $t);
    return $jam ? $s . ' ' . date('H:i', $t) : $s;
}

function label(string $s): string
{
    return strtr(ucwords(str_replace('_', ' ', $s)), ['Qris' => 'QRIS', 'E Wallet' => 'E-Wallet']);
}

function status_badge(string $status): string
{
    $warna = [
        'menunggu_pembayaran' => 'warning text-dark',
        'dibayar'             => 'info text-dark',
        'dikirim'             => 'primary',
        'selesai'             => 'success',
        'dibatalkan'          => 'secondary',
        'proses'              => 'warning text-dark',
        'diajukan'            => 'warning text-dark',
        'diterima'            => 'info text-dark',
        'ditolak'             => 'danger',
        'AMAN'                => 'success',
        'MENIPIS'             => 'warning text-dark',
        'HABIS'               => 'danger',
    ][$status] ?? 'light text-dark';
    return '<span class="badge bg-' . $warna . '">' . e(label($status)) . '</span>';
}

function ongkir_untuk(string $kota): int
{
    $map = config('ongkir');
    foreach ($map as $k => $v) {
        if (strcasecmp($k, trim($kota)) === 0) {
            return (int) $v;
        }
    }
    return (int) $map['default'];
}

/** Ikon Bootstrap untuk tiap kategori (dipakai sebagai gambar produk). */
function ikon_kategori(?string $kategori): string
{
    return [
        'Sneakers'   => 'bi-lightning-charge',
        'Heels'      => 'bi-gem',
        'Flat Shoes' => 'bi-flower1',
        'Sandal'     => 'bi-sun',
    ][$kategori ?? ''] ?? 'bi-bag-heart';
}

// ---------------------------------------------------------------------
//  Autentikasi
// ---------------------------------------------------------------------
function admin(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function require_admin(): array
{
    if (!admin()) {
        redirect('admin/login.php');
    }
    return admin();
}

function pelanggan(): ?array
{
    return $_SESSION['pelanggan'] ?? null;
}

function require_pelanggan(): array
{
    if (!pelanggan()) {
        flash('warning', 'Silakan masuk terlebih dahulu.');
        $_SESSION['setelah_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('masuk.php');
    }
    return pelanggan();
}

/**
 * Cek password. Mendukung hash bcrypt (password_hash) dan hash SHA-256
 * dari data contoh; hash lama otomatis ditingkatkan ke bcrypt.
 */
function cek_password(string $plain, string $hash, callable $simpan_hash_baru): bool
{
    if (password_verify($plain, $hash)) {
        return true;
    }
    if (strlen($hash) === 64 && hash_equals($hash, hash('sha256', $plain))) {
        $simpan_hash_baru(password_hash($plain, PASSWORD_DEFAULT));
        return true;
    }
    return false;
}
