<?php
include 'config.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mitra_index.php');
    exit;
}

$nama       = trim($_POST['nama'] ?? '');
$alamat     = trim($_POST['alamat'] ?? '');
$telepon    = trim($_POST['telepon'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');

if ($nama === '') {
    flash_dan_pindah('error', 'Gagal', 'Nama mitra wajib diisi.', 'mitra_tambah.php');
}
if (mb_strlen($nama) > 100 || mb_strlen($telepon) > 30) {
    flash_dan_pindah('error', 'Gagal', 'Nama maksimal 100 karakter dan telepon maksimal 30 karakter.', 'mitra_tambah.php');
}

// Kolom opsional: simpan NULL jika kosong
$alamat     = $alamat === '' ? null : $alamat;
$telepon    = $telepon === '' ? null : $telepon;
$keterangan = $keterangan === '' ? null : $keterangan;

try {
    $stmt = $conn->prepare("INSERT INTO mitra (nama, alamat, telepon, keterangan) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $nama, $alamat, $telepon, $keterangan);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat disimpan: ' . $e->getMessage(), 'mitra_tambah.php');
}

flash_dan_pindah('success', 'Berhasil', 'Mitra berhasil ditambahkan.', 'mitra_index.php');