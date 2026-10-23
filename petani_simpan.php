<?php
include 'config.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: petani_index.php');
    exit;
}

$nama    = trim($_POST['nama'] ?? '');
$alamat  = trim($_POST['alamat'] ?? '');
$telepon = trim($_POST['telepon'] ?? '');
$user_id = (int)($_SESSION['sipk-user_id'] ?? 0);

if ($nama === '') {
    flash_dan_pindah('error', 'Gagal', 'Nama petani wajib diisi.', 'petani_tambah.php');
}
if (mb_strlen($nama) > 100 || mb_strlen($telepon) > 30) {
    flash_dan_pindah('error', 'Gagal', 'Nama maksimal 100 karakter dan telepon maksimal 30 karakter.', 'petani_tambah.php');
}

// Kolom opsional: simpan NULL jika kosong
$alamat  = $alamat === '' ? null : $alamat;
$telepon = $telepon === '' ? null : $telepon;

try {
    // created_at terisi otomatis oleh database
    $stmt = $conn->prepare("INSERT INTO petani (nama, alamat, telepon, user_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('sssi', $nama, $alamat, $telepon, $user_id);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat disimpan: ' . $e->getMessage(), 'petani_tambah.php');
}

flash_dan_pindah('success', 'Berhasil', 'Petani berhasil ditambahkan.', 'petani_index.php');