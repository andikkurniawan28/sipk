<?php
include 'config.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: komplain_index.php');
    exit;
}

$tanggal      = trim($_POST['tanggal'] ?? '');
$kode         = trim($_POST['kode'] ?? '');
$pelanggan_id = (int)($_POST['pelanggan_id'] ?? 0);
$keterangan   = trim($_POST['keterangan'] ?? '');
$user_id      = (int)($_SESSION['sipk-user_id'] ?? 0);

if ($tanggal === '' || $kode === '' || $pelanggan_id <= 0 || $keterangan === '') {
    flash_dan_pindah('error', 'Gagal', 'Semua kolom wajib diisi dengan benar.', 'komplain_tambah.php');
}

try {
    $stmt = $conn->prepare("INSERT INTO komplain (tanggal, kode, pelanggan_id, keterangan, user_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('ssisi', $tanggal, $kode, $pelanggan_id, $keterangan, $user_id);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat disimpan: ' . $e->getMessage(), 'komplain_tambah.php');
}

flash_dan_pindah('success', 'Berhasil', 'Data komplain berhasil ditambahkan.', 'komplain_index.php');