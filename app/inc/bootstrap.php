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
            // Samakan collation koneksi dengan database (utf8mb4_unicode_ci, lihat 01_schema.sql).
            // Tanpa ini, server dengan default utf8mb4_general_ci (mis. XAMPP) memunculkan
            // error 1267 "Illegal mix of collations" saat teks dibandingkan dengan kolom view.
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
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
        return 'Data ini masih dipakai di transaksi atau data lain, jadi tidak bisa dihapus/diubah.';
    }
    if ($code === 1452) {
        return 'Data yang dipilih (referensi) tidak ditemukan.';
    }
    if ($code === 4025 || $code === 3819) {
        return pesan_constraint($msg);
    }
    if ($code === 1054 || $code === 1146) {
        // kolom / tabel belum ada: biasanya file SQL tambahan belum di-import
        $kolom = preg_match("/'([^']+)'/", $msg, $m) ? $m[1] : '';
        $file  = [
            'password_hash' => '08_akun_pelanggan.sql',
            'retur'         => '06_retur.sql',
            'detail_retur'  => '06_retur.sql',
        ];
        $saran = '';
        foreach ($file as $nama => $sql) {
            if (str_contains($kolom, $nama)) {
                $saran = " Import file database/$sql di phpMyAdmin, lalu coba lagi.";
                break;
            }
        }
        return "Struktur database belum lengkap (\"$kolom\" tidak ditemukan)." . ($saran ?: ' Pastikan semua file di folder database/ sudah di-import berurutan.');
    }
    if ($code === 1048) {
        return preg_match("/Column '([^']+)'/", $msg, $m) ? 'Kolom "' . label($m[1]) . '" wajib diisi.' : 'Ada data wajib yang belum diisi.';
    }
    if ($code === 1406) {
        return preg_match("/column '([^']+)'/", $msg, $m) ? 'Isian "' . label($m[1]) . '" terlalu panjang.' : 'Ada isian yang terlalu panjang.';
    }
    if (in_array($code, [1292, 1366, 1265], true)) {
        return 'Format isian tidak valid (periksa tanggal atau angka).';
    }
    return 'Database menolak perintah ini (' . $msg . ').';
}

/** Terjemahkan pelanggaran CHECK constraint menjadi kalimat biasa. */
function pesan_constraint(string $msg): string
{
    $daftar = [
        'chk_varian_ukuran'      => 'Ukuran harus di antara 30 dan 50.',
        'chk_varian_stok'        => 'Stok produk tidak boleh kurang dari 0.',
        'chk_varian_stokmin'     => 'Stok minimum tidak boleh negatif.',
        'chk_bahan_stok'         => 'Stok bahan baku tidak boleh kurang dari 0.',
        'chk_bahan_stokmin'      => 'Stok minimum tidak boleh negatif.',
        'chk_produk_harga'       => 'Harga jual tidak boleh negatif.',
        'chk_dpb_jumlah'         => 'Jumlah pembelian harus lebih dari 0.',
        'chk_dpb_harga'          => 'Harga satuan tidak boleh negatif.',
        'chk_pakai_jumlah'       => 'Jumlah bahan yang dipakai harus lebih dari 0.',
        'chk_hasil_jumlah'       => 'Jumlah hasil produksi harus lebih dari 0.',
        'chk_dpj_jumlah'         => 'Jumlah pesanan harus lebih dari 0.',
        'chk_dpj_harga'          => 'Harga tidak boleh negatif.',
        'chk_penjualan_ongkir'   => 'Ongkir tidak boleh negatif.',
        'chk_produksi_tanggal'   => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        'chk_pengiriman_tanggal' => 'Tanggal terima tidak boleh sebelum tanggal kirim.',
        'chk_retur_tanggal'      => 'Tanggal selesai retur tidak boleh sebelum tanggal pengajuan.',
        'chk_dretur_jumlah'      => 'Jumlah barang retur harus lebih dari 0.',
    ];
    foreach ($daftar as $nama => $kalimat) {
        if (str_contains($msg, $nama)) {
            return $kalimat;
        }
    }
    return 'Nilai yang diisi melanggar aturan data.';
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

/**
 * Simpan notifikasi untuk halaman berikutnya.
 * $type: success | danger | warning. $judul opsional, default:
 * Berhasil / Gagal / Perhatian. Pesan 'danger' ditampilkan sebagai "Alasan: ...".
 */
function flash(string $type, string $message, ?string $judul = null): void
{
    $_SESSION['flash'][] = [$type, $message, $judul];
}

function render_flash(): string
{
    $default = ['success' => 'Berhasil', 'danger' => 'Gagal', 'warning' => 'Perhatian', 'info' => 'Info'];
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        [$type, $msg] = $f;
        $judul = $f[2] ?? $default[$type] ?? 'Info';
        $isi   = $type === 'danger' ? '<span class="alert-reason">Alasan:</span> ' . e($msg) : e($msg);
        $html .= '<div class="alert alert-' . e($type) . ' alert-dismissible fade show" role="' . ($type === 'danger' ? 'alert' : 'status') . '">'
            . '<div class="alert-title">' . e($judul) . '</div><div>' . $isi . '</div>'
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>';
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

/** Status sebagai titik berwarna + teks (warna hanya penanda, teks tetap terbaca). */
function status_badge(string $status): string
{
    $tone = [
        'menunggu_pembayaran' => 'warn',
        'proses'              => 'warn',
        'diajukan'            => 'warn',
        'MENIPIS'             => 'warn',
        'dibayar'             => 'info',
        'dikirim'             => 'info',
        'diterima'            => 'info',
        'selesai'             => 'ok',
        'AMAN'                => 'ok',
        'baik'                => 'ok',
        'ya'                  => 'ok',
        'ditolak'             => 'bad',
        'HABIS'               => 'bad',
        'cacat'               => 'bad',
        'dibatalkan'          => 'off',
        'tidak'               => 'off',
    ][$status] ?? 'off';
    $teks = ctype_upper(str_replace('_', '', $status)) ? ucfirst(strtolower($status)) : label($status);
    return '<span class="status status-' . $tone . '">' . e($teks) . '</span>';
}

/** Tag <link> untuk CSS, font, dan ikon, dipakai semua halaman. */
function head_assets(): string
{
    return '<link rel="preconnect" href="https://fonts.googleapis.com">'
        . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">'
        . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">'
        . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">'
        . '<link rel="stylesheet" href="' . url('assets/app.css') . '?v=' . filemtime(__DIR__ . '/../assets/app.css') . '">';
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

// ---------------------------------------------------------------------
//  Foto produk (BLOB di tabel produk_foto, file 09_foto_produk.sql)
// ---------------------------------------------------------------------
const FOTO_MAKS_BYTE = 921600;   // 900 KB (max_allowed_packet XAMPP = 1 MB)
const FOTO_TIPE      = ['image/jpeg' => 'JPG', 'image/png' => 'PNG', 'image/webp' => 'WebP'];

/** True jika tabel produk_foto sudah ada (file 09 sudah di-import). */
function foto_siap(): bool
{
    static $siap = null;
    return $siap ??= (bool) val("SELECT COUNT(*) FROM information_schema.tables
                                   WHERE table_schema = DATABASE() AND table_name = 'produk_foto'");
}

/**
 * Potongan SELECT "versi foto" (timestamp unggah, NULL jika tidak ada foto)
 * untuk produk beralias $alias. Aman dipakai walau tabel foto belum ada.
 */
function sql_foto_versi(string $alias = 'p'): string
{
    return foto_siap()
        ? "(SELECT UNIX_TIMESTAMP(f.diunggah_pada) FROM produk_foto f WHERE f.id_produk = $alias.id_produk)"
        : 'NULL';
}

/**
 * Simpan (atau ganti) foto produk dari $_FILES ke kolom BLOB.
 * Melempar RuntimeException dengan alasan yang jelas jika file ditolak.
 */
function simpan_foto_produk(int $id_produk, array $file): void
{
    if (!foto_siap()) {
        throw new RuntimeException('Tabel foto belum ada di database. Import file database/09_foto_produk.sql di phpMyAdmin, lalu coba lagi.');
    }
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Belum ada file foto yang dipilih.');
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('Ukuran file melebihi batas unggah server.');
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException("File gagal diunggah (kode $err). Silakan coba lagi.");
    }

    $ukuran = filesize($file['tmp_name']);
    if ($ukuran > FOTO_MAKS_BYTE) {
        throw new RuntimeException(sprintf('Ukuran foto %s KB, maksimal %d KB. Perkecil foto lalu unggah lagi.',
            number_format($ukuran / 1024, 0, ',', '.'), FOTO_MAKS_BYTE / 1024));
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(FOTO_TIPE[$mime])) {
        throw new RuntimeException('Format file harus JPG, PNG, atau WebP (file yang dipilih: ' . $mime . ').');
    }
    $dimensi = @getimagesize($file['tmp_name']);
    if (!$dimensi) {
        throw new RuntimeException('File tidak bisa dibaca sebagai gambar.');
    }

    $st = db()->prepare('INSERT INTO produk_foto (id_produk, foto, tipe_mime, ukuran_byte, lebar, tinggi)
                         VALUES (?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE foto = VALUES(foto), tipe_mime = VALUES(tipe_mime),
                           ukuran_byte = VALUES(ukuran_byte), lebar = VALUES(lebar), tinggi = VALUES(tinggi),
                           diunggah_pada = CURRENT_TIMESTAMP');
    $st->bindValue(1, $id_produk, PDO::PARAM_INT);
    $st->bindValue(2, file_get_contents($file['tmp_name']), PDO::PARAM_LOB);   // data biner
    $st->bindValue(3, $mime);
    $st->bindValue(4, $ukuran, PDO::PARAM_INT);
    $st->bindValue(5, min($dimensi[0], 65535), PDO::PARAM_INT);
    $st->bindValue(6, min($dimensi[1], 65535), PDO::PARAM_INT);
    $st->execute();
}

function url_foto(int $id_produk, $versi): string
{
    return url('foto.php?id=' . $id_produk . '&v=' . (int) $versi);
}

/**
 * Gambar produk: foto asli jika ada, selain itu kotak tipografis.
 * $p butuh: id_produk, nama_produk, kode_produk, nama_kategori, foto_versi.
 */
function produk_media(array $p, string $size = '', string $tag = ''): string
{
    if (empty($p['foto_versi'])) {
        return produk_tile($p['nama_produk'], $size === 'sm' ? '' : $p['kode_produk'], $p['nama_kategori'], $size, $tag);
    }
    return '<div class="tile tile-photo' . ($size ? ' tile-' . $size : '') . '">'
        . '<img src="' . e(url_foto((int) $p['id_produk'], $p['foto_versi'])) . '" alt="' . e($p['nama_produk']) . '" loading="lazy">'
        . ($tag ? '<span class="tag tag-accent">' . e($tag) . '</span>' : '')
        . '</div>';
}

/**
 * Kotak produk tipografis, pengganti foto bila produk belum punya foto.
 * Warna latar netral dipilih tetap per kategori. $size: '' | 'lg' | 'sm'.
 */
function produk_tile(string $nama, string $kode, string $kategori, string $size = '', string $tag = ''): string
{
    $tetap = ['Sneakers' => '#e1e4dc', 'Heels' => '#eadfd9', 'Flat Shoes' => '#e9e3d3', 'Sandal' => '#dfe3e7'];
    $tones = ['#ece6dd', '#e6dde3', '#e3e1d6', '#dde4e1'];
    $tone  = $tetap[$kategori] ?? $tones[hexdec(substr(md5($kategori), 0, 6)) % count($tones)];
    if ($size === 'sm') {
        $inisial = implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(explode(' ', $nama), 0, 2)));
        return '<div class="tile tile-sm" style="--tone:' . $tone . '" aria-hidden="true"><div class="tile-name">' . e($inisial) . '</div></div>';
    }
    return '<div class="tile' . ($size ? ' tile-' . $size : '') . '" style="--tone:' . $tone . '">'
        . '<div class="tile-cat">' . e($kategori) . '</div>'
        . '<div class="tile-code">' . e($kode) . '</div>'
        . '<div class="tile-name">' . e($nama) . '</div>'
        . ($tag ? '<span class="tag tag-accent">' . e($tag) . '</span>' : '')
        . '</div>';
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
