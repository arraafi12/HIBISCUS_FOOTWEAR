<?php
require __DIR__ . '/inc/toko.php';

unset($_SESSION['pelanggan']);
session_regenerate_id(true);
flash('success', 'Anda sudah keluar.');
redirect('index.php');
