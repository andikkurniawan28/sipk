<?php
include 'config.php';

// Hapus hanya session milik aplikasi ini (prefix sipk-), agar aplikasi lain
// yang berbagi session di server yang sama tidak ikut ter-logout.
foreach (array_keys($_SESSION) as $kunci) {
    if (strpos((string)$kunci, 'sipk-') === 0) {
        unset($_SESSION[$kunci]);
    }
}

// Ganti ID session setelah logout
session_regenerate_id(true);

$_SESSION['flash'] = [
    'icon'  => 'success',
    'title' => 'Logout',
    'text'  => 'Anda telah keluar dari sistem.'
];

header('Location: login.php');
exit;