<?php
/**
 * File Report: menampilkan 7 laporan dari VIEW dan STORED PROCEDURE database.
 */
require __DIR__ . '/../inc/admin.php';
require_admin();

$jenis = get('jenis', 'penjualan');
$awal  = get('awal', date('Y-m-01', strtotime('-2 months')));
$akhir = get('akhir', date('Y-m-t'));
$idSup = get('supplier');
$limit = max(1, (int) get('limit', 10));

$laporan = [
    'pembelian'   => ['Laporan Pembelian', true],
    'stok_bahan'  => ['Laporan Stok Bahan Baku', false],
    'stok_produk' => ['Laporan Stok Produk Jadi', false],
    'produksi'    => ['Laporan Produksi', true],
    'penjualan'   => ['Laporan Penjualan', true],
    'terlaris'    => ['Laporan Produk Terlaris', true],
    'retur'       => ['Laporan Retur', true],
];
if (!isset($laporan[$jenis])) {
    $jenis = 'penjualan';
}
[$judul, $pakaiPeriode] = $laporan[$jenis];

// Setiap bagian: [subjudul, baris, tampilkan_total, sumber]
$bagian = [];
switch ($jenis) {
    case 'pembelian':
        [$rinci, $rekap] = call_proc('CALL sp_laporan_pembelian(?, ?, ?)', [$awal, $akhir, $idSup === '' ? null : (int) $idSup]);
        $bagian[] = ['Rekap per Supplier', $rekap, true, 'sp_laporan_pembelian'];
        $bagian[] = ['Rincian Pembelian', $rinci, true, 'sp_laporan_pembelian'];
        $bagian[] = ['Rekap Bulanan', rows('SELECT * FROM v_rekap_pembelian_bulanan WHERE periode BETWEEN ? AND ? ORDER BY periode, nama_supplier',
                     [substr($awal, 0, 7), substr($akhir, 0, 7)]), true, 'v_rekap_pembelian_bulanan'];
        break;
    case 'stok_bahan':
        $bagian[] = ['Persediaan Bahan Baku', rows('SELECT * FROM v_laporan_stok_bahan_baku ORDER BY kode_bahan'), true, 'v_laporan_stok_bahan_baku'];
        break;
    case 'stok_produk':
        $bagian[] = ['Ringkasan per Produk', rows('SELECT kode_produk, nama_produk, nama_kategori, SUM(stok) AS stok,
                         SUM(nilai_persediaan) AS nilai_persediaan, SUM(status_stok <> "AMAN") AS ukuran_perlu_produksi
                       FROM v_laporan_stok_produk GROUP BY kode_produk, nama_produk, nama_kategori ORDER BY kode_produk'), true, 'v_laporan_stok_produk'];
        $bagian[] = ['Stok per Ukuran', rows('SELECT kode_produk, nama_produk, ukuran, sku, stok, stok_minimum, harga_jual, nilai_persediaan, status_stok
                       FROM v_laporan_stok_produk ORDER BY kode_produk, ukuran'), true, 'v_laporan_stok_produk'];
        break;
    case 'produksi':
        [$rinci, $bahan] = call_proc('CALL sp_laporan_produksi(?, ?)', [$awal, $akhir]);
        $bagian[] = ['Rincian Produksi', $rinci, true, 'sp_laporan_produksi'];
        $bagian[] = ['Bahan Baku Terpakai', $bahan, false, 'sp_laporan_produksi'];
        break;
    case 'penjualan':
        [$rinci, $ringkas] = call_proc('CALL sp_laporan_penjualan(?, ?)', [$awal, $akhir]);
        $bagian[] = ['Ringkasan Periode', $ringkas, false, 'sp_laporan_penjualan'];
        $bagian[] = ['Rekap Bulanan', rows('SELECT * FROM v_rekap_penjualan_bulanan WHERE periode BETWEEN ? AND ? ORDER BY periode',
                     [substr($awal, 0, 7), substr($akhir, 0, 7)]), true, 'v_rekap_penjualan_bulanan'];
        $bagian[] = ['Rincian Pesanan', $rinci, false, 'sp_laporan_penjualan'];
        break;
    case 'terlaris':
        [$top] = call_proc('CALL sp_produk_terlaris(?, ?, ?)', [$awal, $akhir, $limit]);
        $bagian[] = ['Peringkat Produk (bersih setelah retur)', $top, true, 'sp_produk_terlaris'];
        $bagian[] = ['Peringkat per Ukuran (semua periode)', rows('SELECT * FROM v_produk_terlaris_per_ukuran ORDER BY peringkat LIMIT ' . $limit), false, 'v_produk_terlaris_per_ukuran'];
        break;
    case 'retur':
        [$rinci, $ringkas] = call_proc('CALL sp_laporan_retur(?, ?)', [$awal, $akhir]);
        $bagian[] = ['Ringkasan Periode', $ringkas, false, 'sp_laporan_retur'];
        $bagian[] = ['Rekap Bulanan', rows('SELECT * FROM v_rekap_retur_bulanan WHERE periode BETWEEN ? AND ? ORDER BY periode',
                     [substr($awal, 0, 7), substr($akhir, 0, 7)]), true, 'v_rekap_retur_bulanan'];
        $bagian[] = ['Rincian Retur', $rinci, false, 'sp_laporan_retur'];
        break;
}

/** Jenis format kolom berdasarkan namanya. */
function jenis_kolom(string $c): string
{
    if (preg_match('/^id_/', $c)) return 'skip';
    if (preg_match('/harga|subtotal|omzet|refund|pendapatan|nilai|ongkir|penjualan_produk|total_pembelian|^total$/', $c)) return 'uang';
    if (preg_match('/tanggal/', $c)) return 'tanggal';
    if (preg_match('/^status/', $c)) return 'status';
    if (preg_match('/jumlah|stok|pasang|terjual|dipakai|peringkat|retur_|batch|cacat|perlu|ukuran$/', $c)) return 'angka';
    return 'teks';
}

admin_header($judul, 'lap_' . $jenis);
page_title($judul, '<button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>');
?>
<div class="d-none d-print-block mb-3">
  <h2 class="h5 mb-0"><?= e(config('app_name')) ?></h2>
  <div><?= e($judul) ?><?= $pakaiPeriode ? ' · Periode ' . e(tgl($awal)) . ' s/d ' . e(tgl($akhir)) : ' · Per ' . e(tgl(date('Y-m-d'))) ?></div>
</div>

<?php if ($pakaiPeriode): ?>
<form class="card card-body mb-3 d-print-none" method="get">
  <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
  <div class="row g-2 align-items-end">
    <div class="col-auto"><label class="form-label small mb-1">Dari</label><input class="form-control" type="date" name="awal" value="<?= e($awal) ?>"></div>
    <div class="col-auto"><label class="form-label small mb-1">Sampai</label><input class="form-control" type="date" name="akhir" value="<?= e($akhir) ?>"></div>
    <?php if ($jenis === 'pembelian'): ?>
      <div class="col-auto"><label class="form-label small mb-1">Supplier</label>
        <select class="form-select" name="supplier"><option value="">Semua supplier</option>
          <?php foreach (options('SELECT id_supplier, nama_supplier FROM supplier ORDER BY nama_supplier') as $v => $t): ?>
            <option value="<?= e($v) ?>" <?= (string) $idSup === (string) $v ? 'selected' : '' ?>><?= e($t) ?></option>
          <?php endforeach; ?>
        </select></div>
    <?php endif; ?>
    <?php if ($jenis === 'terlaris'): ?>
      <div class="col-auto"><label class="form-label small mb-1">Jumlah peringkat</label><input class="form-control" type="number" min="1" name="limit" value="<?= e($limit) ?>" style="width:110px"></div>
    <?php endif; ?>
    <div class="col-auto"><button class="btn btn-hibiscus"><i class="bi bi-funnel"></i> Tampilkan</button></div>
  </div>
</form>
<?php endif; ?>

<?php foreach ($bagian as [$sub, $data, $pakaiTotal, $sumber]): ?>
  <div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between">
      <span class="fw-semibold"><?= e($sub) ?></span>
      <code class="small text-muted d-print-none"><?= e($sumber) ?></code>
    </div>
    <div class="table-responsive">
      <?php if (!$data): ?>
        <div class="p-4 text-center text-muted">Tidak ada data.</div>
      <?php else:
        $kolom = array_filter(array_keys($data[0]), fn($c) => jenis_kolom($c) !== 'skip'); ?>
        <table class="table table-sm table-striped mb-0">
          <thead class="table-light"><tr>
            <?php foreach ($kolom as $c): ?>
              <th class="<?= in_array(jenis_kolom($c), ['uang', 'angka'], true) ? 'num' : '' ?>"><?= e(label($c)) ?></th>
            <?php endforeach; ?>
          </tr></thead>
          <tbody>
          <?php foreach ($data as $r): ?>
            <tr>
              <?php foreach ($kolom as $c): $v = $r[$c]; $j = jenis_kolom($c); ?>
                <td class="<?= in_array($j, ['uang', 'angka'], true) ? 'num' : '' ?>"><?php
                  if ($v === null)          echo '<span class="text-muted">-</span>';
                  elseif ($j === 'uang')    echo rupiah($v);
                  elseif ($j === 'angka' && is_numeric($v)) echo angka($v);
                  elseif ($j === 'tanggal') echo e(tgl($v, strlen((string) $v) > 10));
                  elseif ($j === 'status')  echo status_badge((string) $v);
                  else                      echo e($v);
                ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <?php if ($pakaiTotal && count($data) > 1): ?>
            <tfoot><tr class="fw-bold">
              <?php foreach (array_values($kolom) as $i => $c):
                $j = jenis_kolom($c);
                $jumlahkan = $j === 'uang' && !preg_match('/harga/', $c)
                          || ($j === 'angka' && preg_match('/jumlah_transaksi|pasang|terjual|^stok$|retur|cacat/', $c) && $c !== 'stok_minimum'); ?>
                <td class="num"><?= $i === 0 ? '<span class="float-start">Total</span>'
                    : ($jumlahkan ? ($j === 'uang' ? rupiah(array_sum(array_column($data, $c))) : angka(array_sum(array_column($data, $c)))) : '') ?></td>
              <?php endforeach; ?>
            </tr></tfoot>
          <?php endif; ?>
        </table>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
<?php admin_footer();
