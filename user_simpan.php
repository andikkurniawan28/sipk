<?php
include 'config.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: user_index.php');
    exit;
}

$nama      = trim($_POST['nama'] ?? '');
$username  = trim($_POST['username'] ?? '');
$role      = trim($_POST['role'] ?? '');
$password  = $_POST['password'] ?? '';
$password2 = $_POST['password2'] ?? '';
$aktif     = isset($_POST['aktif']) ? 1 : 0;

if ($nama === '' || $username === '' || $role === '' || $password === '') {
    flash_dan_pindah('error', 'Gagal', 'Nama, username, role, dan password wajib diisi.', 'user_tambah.php');
}
if (mb_strlen($nama) > 100 || mb_strlen($role) > 30) {
    flash_dan_pindah('error', 'Gagal', 'Nama maksimal 100 karakter dan role maksimal 30 karakter.', 'user_tambah.php');
}
if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $username)) {
    flash_dan_pindah('error', 'Gagal', 'Username 3-50 karakter: huruf, angka, titik, atau underscore.', 'user_tambah.php');
}
if (strlen($password) < 6) {
    flash_dan_pindah('error', 'Gagal', 'Password minimal 6 karakter.', 'user_tambah.php');
}
if (strlen($password) > 72) {
    // bcrypt hanya memakai 72 byte pertama
    flash_dan_pindah('error', 'Gagal', 'Password maksimal 72 karakter.', 'user_tambah.php');
}
if ($password !== $password2) {
    flash_dan_pindah('error', 'Gagal', 'Konfirmasi password tidak sama.', 'user_tambah.php');
}

$hash = password_hash($password, PASSWORD_BCRYPT);

try {
    $stmt = $conn->prepare("INSERT INTO `user` (nama, username, password, role, aktif) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('ssssi', $nama, $username, $hash, $role, $aktif);
    $stmt->execute();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    if ((int)$e->getCode() === 1062) {
        flash_dan_pindah('error', 'Gagal', 'Username sudah dipakai, gunakan username lain.', 'user_tambah.php');
    }
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat disimpan: ' . $e->getMessage(), 'user_tambah.php');
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat disimpan: ' . $e->getMessage(), 'user_tambah.php');
}

flash_dan_pindah('success', 'Berhasil', 'User berhasil ditambahkan.', 'user_index.php');