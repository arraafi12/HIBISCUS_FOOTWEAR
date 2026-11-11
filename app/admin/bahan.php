<?php
require __DIR__ . '/../inc/admin.php';
require_admin();

crud_page([
    'table'  => 'bahan_baku',
    'pk'     => 'id_bahan',
    'title'  => 'Bahan Baku',
    'active' => 'bahan',
    'order'  => 'kode_bahan',
    'search' => ['kode_bahan', 'nama_bahan'],
    'columns' => [
        'kode_bahan'     => ['label' => 'Kode'],
        'nama_bahan'     => ['label' => 'Nama Bahan'],
        'satuan'         => ['label' => 'Satuan'],
        'stok'           => ['label' => 'Stok', 'format' => 'angka'],
        'stok_minimum'   => ['label' => 'Stok Min.', 'format' => 'angka'],
        'harga_terakhir' => ['label' => 'Harga Terakhir', 'format' => 'rupiah'],
        'status'         => ['label' => 'Status', 'format' => fn($r) => status_badge(
            $r['stok'] == 0 ? 'HABIS' : ($r['stok'] <= $r['stok_minimum'] ? 'MENIPIS' : 'AMAN'))],
    ],
    'fields' => [
        'kode_bahan'   => ['label' => 'Kode Bahan', 'required' => true, 'help' => 'Contoh: BB-009'],
        'nama_bahan'   => ['label' => 'Nama Bahan', 'required' => true],
        'satuan'       => ['label' => 'Satuan', 'type' => 'select', 'required' => true, 'options' => [
            'meter' => 'meter', 'pasang' => 'pasang', 'liter' => 'liter', 'gulung' => 'gulung',
            'pcs' => 'pcs', 'kg' => 'kg', 'lembar' => 'lembar']],
        'stok_minimum' => ['label' => 'Stok Minimum', 'type' => 'number', 'step' => '0.01', 'default' => 0,
                           'help' => 'Stok bertambah otomatis dari Pembelian dan berkurang dari Produksi.'],
    ],
]);
