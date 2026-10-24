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
    flash_dan_pindah('error', 'Gagal', 'ID wilayah tidak valid.', 'wilayah_index.php');
}

try {
    $stmt = $conn->prepare("DELETE FROM wilayah WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $terhapus = $stmt->affected_rows;
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Wilayah tidak dapat dihapus, mungkin sudah dipakai di data lain.', 'wilayah_index.php');
}

if ($terhapus > 0) {
    flash_dan_pindah('success', 'Berhasil', 'Wilayah berhasil dihapus.', 'wilayah_index.php');
}

flash_dan_pindah('error', 'Tidak ditemukan', 'Data wilayah tidak ada.', 'wilayah_index.php');