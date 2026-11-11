<?php
require __DIR__ . '/../inc/bootstrap.php';

unset($_SESSION['admin']);
session_regenerate_id(true);
redirect('admin/login.php');
