<?php
require __DIR__ . '/../inc/admin.php';
require_admin();

crud_page([
    'table'  => 'produk',
    'pk'     => 'id_produk',
    'title'  => 'Produk',
    'active' => 'produk',
    'order'  => 'kode_produk',
    'search' => ['kode_produk', 'nama_produk', 'nama_kategori'],
    'list_sql' => 'SELECT p.*, k.nama_kategori,
                          (SELECT COUNT(*) FROM produk_ukuran u WHERE u.id_produk = p.id_produk) AS jumlah_ukuran,
                          (SELECT COALESCE(SUM(stok),0) FROM produk_ukuran u WHERE u.id_produk = p.id_produk) AS total_stok
                     FROM produk p JOIN kategori k ON k.id_kategori = p.id_kategori',
    'columns' => [
        'kode_produk'   => ['label' => 'Kode'],
        'nama_produk'   => ['label' => 'Nama Produk'],
        'nama_kategori' => ['label' => 'Kategori'],
        'harga_jual'    => ['label' => 'Harga Jual', 'format' => 'rupiah'],
        'jumlah_ukuran' => ['label' => 'Ukuran', 'format' => 'angka'],
        'total_stok'    => ['label' => 'Total Stok', 'format' => 'angka'],
        'aktif'         => ['label' => 'Dijual', 'format' => 'bool'],
    ],
    'row_actions' => fn($r) => '<a class="btn btn-sm btn-outline-secondary" href="produk_ukuran.php?id=' . e($r['id_produk'])
        . '" title="Ukuran & stok"><i class="bi bi-rulers"></i> Ukuran</a>',
    'fields' => [
        'kode_produk' => ['label' => 'Kode Produk', 'required' => true, 'help' => 'Contoh: SNK-003. Dipakai sebagai awalan SKU.'],
        'nama_produk' => ['label' => 'Nama Produk', 'required' => true],
        'id_kategori' => ['label' => 'Kategori', 'type' => 'select', 'required' => true,
                          'options' => options('SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori')],
        'harga_jual'  => ['label' => 'Harga Jual (Rp)', 'type' => 'number', 'step' => '1', 'required' => true],
        'deskripsi'   => ['label' => 'Deskripsi', 'type' => 'textarea'],
        'aktif'       => ['label' => 'Masih dijual di toko', 'type' => 'checkbox'],
    ],
]);
