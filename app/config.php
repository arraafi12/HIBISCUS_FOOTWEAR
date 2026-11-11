<?php
/**
 * Konfigurasi aplikasi HIBISCUS FOOTWEAR.
 * Nilai default cocok untuk XAMPP (user root tanpa password).
 * Koneksi database bisa ditimpa dengan environment variable
 * HIBISCUS_DB_HOST, HIBISCUS_DB_PORT, HIBISCUS_DB_NAME, HIBISCUS_DB_USER, HIBISCUS_DB_PASS.
 */
return [
    'db' => [
        'host' => getenv('HIBISCUS_DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('HIBISCUS_DB_PORT') ?: 3306),
        'name' => getenv('HIBISCUS_DB_NAME') ?: 'hibiscus_footwear',
        'user' => getenv('HIBISCUS_DB_USER') ?: 'root',
        'pass' => getenv('HIBISCUS_DB_PASS') !== false ? getenv('HIBISCUS_DB_PASS') : '',
    ],

    'app_name' => 'HIBISCUS FOOTWEAR',

    // Ongkos kirim (Rp) per kota tujuan; kota lain memakai 'default'
    'ongkir' => [
        'default'         => 25000,
        'Jakarta Selatan' => 15000,
        'Jakarta Pusat'   => 15000,
        'Jakarta Barat'   => 15000,
        'Jakarta Timur'   => 15000,
        'Jakarta Utara'   => 15000,
        'Tangerang'       => 15000,
        'Bandung'         => 20000,
        'Semarang'        => 20000,
        'Yogyakarta'      => 22000,
        'Surabaya'        => 18000,
    ],
];
