<?php
include 'config.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'ID pelanggan tidak valid.', 'pelanggan_index.php');
}

try {
    $stmt = $conn->prepare("DELETE FROM pelanggan WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $terhapus = $stmt->affected_rows;
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Pelanggan tidak dapat dihapus, mungkin sudah dipakai di data lain.', 'pelanggan_index.php');
}

if ($terhapus > 0) {
    flash_dan_pindah('success', 'Berhasil', 'Pelanggan berhasil dihapus.', 'pelanggan_index.php');
}

flash_dan_pindah('error', 'Tidak ditemukan', 'Data pelanggan tidak ada.', 'pelanggan_index.php');