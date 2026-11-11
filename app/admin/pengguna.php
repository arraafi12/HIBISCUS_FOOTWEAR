<?php
require __DIR__ . '/../inc/admin.php';
require_admin();

crud_page([
    'table'  => 'pengguna',
    'pk'     => 'id_pengguna',
    'title'  => 'Pengguna',
    'active' => 'pengguna',
    'order'  => 'username',
    'search' => ['username', 'nama_lengkap'],
    'columns' => [
        'username'     => ['label' => 'Username'],
        'nama_lengkap' => ['label' => 'Nama Lengkap'],
        'peran'        => ['label' => 'Peran', 'format' => fn($r) => e(label($r['peran']))],
        'aktif'        => ['label' => 'Aktif', 'format' => 'bool'],
    ],
    'fields' => [
        'username'     => ['label' => 'Username', 'required' => true],
        'nama_lengkap' => ['label' => 'Nama Lengkap', 'required' => true],
        'password'     => ['label' => 'Password', 'type' => 'password', 'required' => true],
        'peran'        => ['label' => 'Peran', 'type' => 'select', 'required' => true, 'options' => [
            'admin' => 'Admin', 'gudang' => 'Gudang', 'produksi' => 'Produksi', 'penjualan' => 'Penjualan']],
        'aktif'        => ['label' => 'Akun aktif', 'type' => 'checkbox'],
    ],
]);
