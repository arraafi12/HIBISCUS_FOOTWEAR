<?php
require __DIR__ . '/../inc/admin.php';
require_admin();

crud_page([
    'table'  => 'pelanggan',
    'pk'     => 'id_pelanggan',
    'title'  => 'Pelanggan',
    'active' => 'pelanggan',
    'order'  => 'nama_pelanggan',
    'search' => ['nama_pelanggan', 'email', 'kota', 'no_hp'],
    'list_sql' => "SELECT p.*, (SELECT COUNT(*) FROM penjualan j WHERE j.id_pelanggan = p.id_pelanggan
                                  AND j.status IN ('dibayar','dikirim','selesai')) AS jumlah_pesanan
                     FROM pelanggan p",
    'columns' => [
        'nama_pelanggan' => ['label' => 'Nama'],
        'email'          => ['label' => 'Email'],
        'no_hp'          => ['label' => 'No. HP'],
        'kota'           => ['label' => 'Kota'],
        'jumlah_pesanan' => ['label' => 'Pesanan', 'format' => 'angka'],
        'terdaftar_pada' => ['label' => 'Terdaftar', 'format' => fn($r) => e(tgl($r['terdaftar_pada']))],
    ],
    'fields' => [
        'nama_pelanggan' => ['label' => 'Nama Pelanggan', 'required' => true],
        'email'          => ['label' => 'Email', 'type' => 'email', 'required' => true],
        'password'       => ['label' => 'Password Login Toko', 'type' => 'password'],
        'no_hp'          => ['label' => 'No. HP', 'required' => true],
        'alamat'         => ['label' => 'Alamat', 'type' => 'textarea', 'required' => true],
        'kota'           => ['label' => 'Kota', 'required' => true],
        'kode_pos'       => ['label' => 'Kode Pos'],
    ],
]);
