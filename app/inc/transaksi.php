<?php
/**
 * Proses transaksi penjualan & retur yang dipakai bersama oleh admin dan toko.
 * Aturan bisnis (stok, lunas, alur status, batas retur) dijaga oleh trigger
 * database; fungsi di sini hanya menyusun perintah dalam satu transaksi.
 * Semua fungsi melempar PDOException jika ditolak database.
 */

function dalam_transaksi(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $hasil = $fn();
        $pdo->commit();
        return $hasil;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Buat pesanan baru. $items = [id_varian => jumlah].
 * Harga diambil otomatis dari harga produk saat ini (snapshot oleh trigger).
 */
function buat_pesanan(int $id_pelanggan, array $items, string $alamat, int $ongkir, ?string $catatan = null): int
{
    $items = array_filter($items, fn($q) => (int) $q > 0);
    if (!$items) {
        throw new InvalidArgumentException('Pesanan belum berisi produk.');
    }
    return dalam_transaksi(function () use ($id_pelanggan, $items, $alamat, $ongkir, $catatan) {
        $no = next_number('penjualan', 'no_pesanan', 'ORD-' . date('Ymd') . '-');
        q('INSERT INTO penjualan (no_pesanan, tanggal_pesan, id_pelanggan, alamat_kirim, ongkir, catatan) VALUES (?, NOW(), ?, ?, ?, ?)',
          [$no, $id_pelanggan, $alamat, $ongkir, $catatan]);
        $id = (int) db()->lastInsertId();
        foreach ($items as $id_varian => $jumlah) {
            q('INSERT INTO detail_penjualan (id_penjualan, id_varian, jumlah, harga_satuan) VALUES (?, ?, ?, NULL)',
              [$id, (int) $id_varian, (int) $jumlah]);
        }
        return $id;
    });
}

/** Bayar lunas: jumlah bayar = total tagihan. */
function bayar_pesanan(int $id, string $metode, ?string $referensi): void
{
    dalam_transaksi(function () use ($id, $metode, $referensi) {
        $total = val('SELECT total FROM penjualan WHERE id_penjualan = ? FOR UPDATE', [$id]);
        q('INSERT INTO pembayaran (id_penjualan, tanggal_bayar, metode, jumlah_bayar, no_referensi) VALUES (?, NOW(), ?, ?, ?)',
          [$id, $metode, $total, $referensi ?: null]);
    });
}

function kirim_pesanan(int $id, string $kurir, ?string $layanan, string $resi, string $tanggal, int $id_pengguna): void
{
    q('INSERT INTO pengiriman (id_penjualan, kurir, layanan, no_resi, tanggal_kirim, id_pengguna) VALUES (?, ?, ?, ?, ?, ?)',
      [$id, $kurir, $layanan ?: null, $resi, $tanggal, $id_pengguna]);
}

function terima_pesanan(int $id, string $tanggal): void
{
    q('UPDATE pengiriman SET tanggal_terima = ? WHERE id_penjualan = ? AND tanggal_terima IS NULL', [$tanggal, $id]);
}

function batalkan_pesanan(int $id, string $alasan): void
{
    q("UPDATE penjualan SET status = 'dibatalkan', catatan = ? WHERE id_penjualan = ?", [$alasan, $id]);
}

/**
 * Ajukan retur. $items = [[id_varian, kondisi, jumlah, id_varian_pengganti|null], ...]
 */
function ajukan_retur(int $id_penjualan, string $jenis, string $alasan, string $tanggal, array $items): int
{
    if (!$items) {
        throw new InvalidArgumentException('Pilih minimal satu barang yang diretur.');
    }
    return dalam_transaksi(function () use ($id_penjualan, $jenis, $alasan, $tanggal, $items) {
        $no = next_number('retur', 'no_retur', 'RT-' . date('Y-m', strtotime($tanggal)) . '-');
        q('INSERT INTO retur (no_retur, id_penjualan, tanggal_pengajuan, jenis, alasan) VALUES (?, ?, ?, ?, ?)',
          [$no, $id_penjualan, $tanggal, $jenis, $alasan]);
        $id = (int) db()->lastInsertId();
        foreach ($items as [$id_varian, $kondisi, $jumlah, $pengganti]) {
            q('INSERT INTO detail_retur (id_retur, id_varian, kondisi, jumlah, harga_satuan, id_varian_pengganti) VALUES (?, ?, ?, ?, NULL, ?)',
              [$id, (int) $id_varian, $kondisi, (int) $jumlah, $pengganti ? (int) $pengganti : null]);
        }
        return $id;
    });
}

/** Pesan error yang ramah untuk ditampilkan. */
function pesan_error(Throwable $e): string
{
    return $e instanceof PDOException ? db_error($e) : $e->getMessage();
}

/** Daftar barang pada pesanan yang masih bisa diretur (jumlah dibeli - sudah diretur). */
function barang_bisa_diretur(int $id_penjualan): array
{
    return rows("SELECT dp.id_varian, dp.jumlah, dp.harga_satuan, pu.ukuran, pu.sku, pu.id_produk, p.nama_produk,
                        dp.jumlah - COALESCE((SELECT SUM(dr.jumlah) FROM detail_retur dr JOIN retur r ON r.id_retur = dr.id_retur
                                               WHERE r.id_penjualan = dp.id_penjualan AND r.status <> 'ditolak'
                                                 AND dr.id_varian = dp.id_varian), 0) AS sisa
                   FROM detail_penjualan dp
                   JOIN produk_ukuran pu ON pu.id_varian = dp.id_varian
                   JOIN produk p ON p.id_produk = pu.id_produk
                  WHERE dp.id_penjualan = ?
                  ORDER BY p.nama_produk, pu.ukuran", [$id_penjualan]);
}

/** Batas akhir pengajuan retur (tanggal terima + 7 hari), atau null. */
function batas_retur(int $id_penjualan): ?string
{
    $t = val('SELECT tanggal_terima FROM pengiriman WHERE id_penjualan = ?', [$id_penjualan]);
    return $t ? date('Y-m-d', strtotime($t . ' +7 days')) : null;
}

/** Isi form retur: jenis, alasan, dan tabel barang (dipakai admin & toko). */
function form_retur_fields(array $barang): string
{
    $pengganti = [];
    foreach (array_unique(array_column($barang, 'id_produk')) as $idp) {
        $pengganti[$idp] = rows('SELECT id_varian, ukuran, stok FROM produk_ukuran WHERE id_produk = ? ORDER BY ukuran', [$idp]);
    }
    ob_start(); ?>
    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <label class="form-label">Jenis retur</label>
        <select class="form-select" name="jenis" id="jenisRetur" required>
          <option value="tukar">Tukar ukuran</option>
          <option value="refund">Refund (uang kembali)</option>
        </select>
      </div>
      <div class="col-md-8">
        <label class="form-label">Alasan <span class="text-danger">*</span></label>
        <input class="form-control" name="alasan" required maxlength="255" placeholder="mis. Ukuran kekecilan / sol lepas">
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead class="table-light"><tr><th>Produk</th><th>Ukuran</th><th class="num">Bisa diretur</th>
          <th style="width:110px">Jml baik</th><th style="width:110px">Jml cacat</th><th class="kolom-tukar" style="width:170px">Tukar ke ukuran</th></tr></thead>
        <tbody>
        <?php foreach ($barang as $b): $k = $b['id_varian']; $dis = $b['sisa'] <= 0 ? 'disabled' : ''; ?>
          <tr>
            <td><?= e($b['nama_produk']) ?></td>
            <td><?= e($b['ukuran']) ?></td>
            <td class="num"><?= angka($b['sisa']) ?> dari <?= angka($b['jumlah']) ?></td>
            <td><input class="form-control form-control-sm" type="number" min="0" max="<?= e($b['sisa']) ?>" name="jumlah[<?= e($k) ?>][baik]" value="0" <?= $dis ?>></td>
            <td><input class="form-control form-control-sm" type="number" min="0" max="<?= e($b['sisa']) ?>" name="jumlah[<?= e($k) ?>][cacat]" value="0" <?= $dis ?>></td>
            <td class="kolom-tukar">
              <select class="form-select form-select-sm" name="pengganti[<?= e($k) ?>]" <?= $dis ?>>
                <option value="">-- pilih --</option>
                <?php foreach ($pengganti[$b['id_produk']] as $p): ?>
                  <option value="<?= e($p['id_varian']) ?>" <?= $p['stok'] <= 0 ? 'disabled' : '' ?>>Ukuran <?= e($p['ukuran']) ?> (stok <?= e($p['stok']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="form-text mb-3">Kondisi <b>baik</b> kembali ke stok jual; kondisi <b>cacat</b> tidak. Tukar hanya ke ukuran lain dari produk yang sama.</div>
    <script>
      (function () {
        var s = document.getElementById('jenisRetur');
        function sync() {
          document.querySelectorAll('.kolom-tukar').forEach(function (el) { el.style.display = s.value === 'tukar' ? '' : 'none'; });
        }
        s.addEventListener('change', sync); sync();
      })();
    </script>
    <?php
    return ob_get_clean();
}

/** Baca baris retur dari form (dipakai admin & toko). */
function baca_form_retur(array $barang, string $jenis): array
{
    $items = [];
    foreach ($barang as $b) {
        $k = $b['id_varian'];
        foreach (['baik', 'cacat'] as $kondisi) {
            $jml = (int) ($_POST['jumlah'][$k][$kondisi] ?? 0);
            if ($jml > 0) {
                $pengganti = $jenis === 'tukar' ? ((int) ($_POST['pengganti'][$k] ?? 0) ?: null) : null;
                $items[] = [$k, $kondisi, $jml, $pengganti];
            }
        }
    }
    return $items;
}
