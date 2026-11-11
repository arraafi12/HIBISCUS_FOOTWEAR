<?php
require __DIR__ . '/../inc/admin.php';
require_admin();

crud_page([
    'table'  => 'kategori',
    'pk'     => 'id_kategori',
    'title'  => 'Kategori',
    'active' => 'kategori',
    'order'  => 'nama_kategori',
    'search' => ['nama_kategori'],
    'list_sql' => 'SELECT k.*, (SELECT COUNT(*) FROM produk p WHERE p.id_kategori = k.id_kategori) AS jumlah_produk
                     FROM kategori k',
    'columns' => [
        'nama_kategori' => ['label' => 'Nama Kategori'],
        'deskripsi'     => ['label' => 'Deskripsi'],
        'jumlah_produk' => ['label' => 'Jumlah Produk', 'format' => 'angka'],
    ],
    'fields' => [
        'nama_kategori' => ['label' => 'Nama Kategori', 'required' => true],
        'deskripsi'     => ['label' => 'Deskripsi', 'type' => 'textarea'],
    ],
]);
