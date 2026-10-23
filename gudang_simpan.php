<?php
include 'config.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: gudang_index.php');
    exit;
}

$nama   = trim($_POST['nama'] ?? '');
$alamat = trim($_POST['alamat'] ?? '');

if ($nama === '') {
    flash_dan_pindah('error', 'Gagal', 'Nama gudang wajib diisi.', 'gudang_tambah.php');
}
if (mb_strlen($nama) > 100) {
    flash_dan_pindah('error', 'Gagal', 'Nama maksimal 100 karakter.', 'gudang_tambah.php');
}

try {
    $stmt = $conn->prepare("INSERT INTO gudang (nama, alamat) VALUES (?, ?)");
    $stmt->bind_param('ss', $nama, $alamat);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat disimpan: ' . $e->getMessage(), 'gudang_tambah.php');
}

flash_dan_pindah('success', 'Berhasil', 'Gudang berhasil ditambahkan.', 'gudang_index.php');