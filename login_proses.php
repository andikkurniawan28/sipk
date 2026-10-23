<?php
include 'config.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Validasi token CSRF
$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['sipk-csrf']) || !hash_equals($_SESSION['sipk-csrf'], $csrf)) {
    flash_dan_pindah('error', 'Gagal', 'Sesi form tidak valid, silakan coba lagi.', 'login.php');
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    flash_dan_pindah('error', 'Login gagal', 'Username dan password wajib diisi.', 'login.php');
}

try {
    $stmt = $conn->prepare("SELECT id, nama, username, password, role, aktif FROM `user` WHERE username = ? LIMIT 1");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Login gagal', 'Terjadi kesalahan pada server.', 'login.php');
}

// Jika username tidak ada, tetap jalankan verifikasi dummy agar waktu respons tidak membocorkan keberadaan username
$hashTersimpan = $user['password'] ?? '$2y$10$usesomesillystringforsaleeeeeuIqfS4Ma3XdUt0z3qLrTZ5RNd8k6';
$passwordCocok = password_verify($password, $hashTersimpan);

if (!$user || !$passwordCocok) {
    flash_dan_pindah('error', 'Login gagal', 'Username atau password salah.', 'login.php');
}

if ((int)$user['aktif'] !== 1) {
    flash_dan_pindah('error', 'Login gagal', 'Akun Anda nonaktif. Hubungi administrator.', 'login.php');
}

// Perbarui hash jika algoritma/cost bcrypt sudah usang
if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
    try {
        $hashBaru = password_hash($password, PASSWORD_BCRYPT);
        $upd = $conn->prepare("UPDATE `user` SET password = ? WHERE id = ?");
        $upd->bind_param('si', $hashBaru, $user['id']);
        $upd->execute();
        $upd->close();
    } catch (Throwable $e) {
        // Gagal rehash tidak menghalangi login
    }
}

// Cegah session fixation
session_regenerate_id(true);

unset($_SESSION['sipk-csrf']);
$_SESSION['sipk-user_id']  = (int)$user['id'];
$_SESSION['sipk-nama']     = $user['nama'];
$_SESSION['sipk-username'] = $user['username'];
$_SESSION['sipk-role']     = $user['role'];

flash_dan_pindah('success', 'Selamat datang', $user['nama'], 'index.php');