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

$id           = (int)($_POST['id'] ?? 0);
$tanggal      = trim($_POST['tanggal'] ?? '');
$kode         = trim($_POST['kode'] ?? '');
$pelanggan_id = (int)($_POST['pelanggan_id'] ?? 0);
$keterangan   = trim($_POST['keterangan'] ?? '');

if ($id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'ID komplain tidak valid.', 'komplain_index.php');
}

if ($tanggal === '' || $kode === '' || $pelanggan_id <= 0 || $keterangan === '') {
    flash_dan_pindah('error', 'Gagal', 'Semua kolom wajib diisi dengan benar.', "komplain_edit.php?id=$id");
}

try {
    $stmt = $conn->prepare("UPDATE komplain SET tanggal = ?, kode = ?, pelanggan_id = ?, keterangan = ? WHERE id = ?");
    $stmt->bind_param('ssisi', $tanggal, $kode, $pelanggan_id, $keterangan, $id);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat diperbarui: ' . $e->getMessage(), "komplain_edit.php?id=$id");
}

flash_dan_pindah('success', 'Berhasil', 'Data komplain berhasil diperbarui.', 'komplain_index.php');