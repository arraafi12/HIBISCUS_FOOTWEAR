<?php
require __DIR__ . '/../inc/admin.php';
require_admin();

crud_page([
    'table'  => 'supplier',
    'pk'     => 'id_supplier',
    'title'  => 'Supplier',
    'active' => 'supplier',
    'order'  => 'nama_supplier',
    'search' => ['nama_supplier', 'kota', 'kontak_person'],
    'columns' => [
        'nama_supplier' => ['label' => 'Nama Supplier'],
        'kontak_person' => ['label' => 'Kontak'],
        'no_telepon'    => ['label' => 'Telepon'],
        'email'         => ['label' => 'Email'],
        'kota'          => ['label' => 'Kota'],
        'aktif'         => ['label' => 'Aktif', 'format' => 'bool'],
    ],
    'fields' => [
        'nama_supplier' => ['label' => 'Nama Supplier', 'required' => true],
        'kontak_person' => ['label' => 'Kontak Person'],
        'no_telepon'    => ['label' => 'No. Telepon', 'required' => true],
        'email'         => ['label' => 'Email', 'type' => 'email'],
        'alamat'        => ['label' => 'Alamat', 'type' => 'textarea', 'required' => true],
        'kota'          => ['label' => 'Kota', 'required' => true],
        'aktif'         => ['label' => 'Masih bekerja sama', 'type' => 'checkbox'],
    ],
]);
