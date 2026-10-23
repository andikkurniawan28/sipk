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
    flash_dan_pindah('error', 'Gagal', 'ID mitra tidak valid.', 'mitra_index.php');
}

try {
    $stmt = $conn->prepare("DELETE FROM mitra WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $terhapus = $stmt->affected_rows;
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Mitra tidak dapat dihapus, mungkin sudah dipakai di data lain.', 'mitra_index.php');
}

if ($terhapus > 0) {
    flash_dan_pindah('success', 'Berhasil', 'Mitra berhasil dihapus.', 'mitra_index.php');
}

flash_dan_pindah('error', 'Tidak ditemukan', 'Data mitra tidak ada.', 'mitra_index.php');