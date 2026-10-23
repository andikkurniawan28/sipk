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

$id        = (int)($_POST['id'] ?? 0);
$nama      = trim($_POST['nama'] ?? '');
$username  = trim($_POST['username'] ?? '');
$role      = trim($_POST['role'] ?? '');
$password  = $_POST['password'] ?? '';
$password2 = $_POST['password2'] ?? '';
$aktif     = isset($_POST['aktif']) ? 1 : 0;

$kembali = 'user_edit.php?id=' . $id;

if ($id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'ID user tidak valid.', 'user_index.php');
}
if ($nama === '' || $username === '' || $role === '') {
    flash_dan_pindah('error', 'Gagal', 'Nama, username, dan role wajib diisi.', $kembali);
}
if (mb_strlen($nama) > 100 || mb_strlen($role) > 30) {
    flash_dan_pindah('error', 'Gagal', 'Nama maksimal 100 karakter dan role maksimal 30 karakter.', $kembali);
}
if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $username)) {
    flash_dan_pindah('error', 'Gagal', 'Username 3-50 karakter: huruf, angka, titik, atau underscore.', $kembali);
}

// Password hanya diganti jika kolom diisi
$gantiPassword = $password !== '';
if ($gantiPassword) {
    if (strlen($password) < 6) {
        flash_dan_pindah('error', 'Gagal', 'Password minimal 6 karakter.', $kembali);
    }
    if (strlen($password) > 72) {
        // bcrypt hanya memakai 72 byte pertama
        flash_dan_pindah('error', 'Gagal', 'Password maksimal 72 karakter.', $kembali);
    }
    if ($password !== $password2) {
        flash_dan_pindah('error', 'Gagal', 'Konfirmasi password tidak sama.', $kembali);
    }
}

try {
    if ($gantiPassword) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE `user` SET nama = ?, username = ?, password = ?, role = ?, aktif = ? WHERE id = ?");
        $stmt->bind_param('ssssii', $nama, $username, $hash, $role, $aktif, $id);
    } else {
        $stmt = $conn->prepare("UPDATE `user` SET nama = ?, username = ?, role = ?, aktif = ? WHERE id = ?");
        $stmt->bind_param('sssii', $nama, $username, $role, $aktif, $id);
    }
    $stmt->execute();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    if ((int)$e->getCode() === 1062) {
        flash_dan_pindah('error', 'Gagal', 'Username sudah dipakai user lain.', $kembali);
    }
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat diperbarui: ' . $e->getMessage(), $kembali);
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat diperbarui: ' . $e->getMessage(), $kembali);
}

flash_dan_pindah('success', 'Berhasil', 'User berhasil diperbarui.', 'user_index.php');