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
    flash_dan_pindah('error', 'Gagal', 'ID gudang tidak valid.', 'gudang_index.php');
}

try {
    $stmt = $conn->prepare("DELETE FROM gudang WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $terhapus = $stmt->affected_rows;
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Gudang tidak dapat dihapus, mungkin sudah dipakai di data lain.', 'gudang_index.php');
}

if ($terhapus > 0) {
    flash_dan_pindah('success', 'Berhasil', 'Gudang berhasil dihapus.', 'gudang_index.php');
}

flash_dan_pindah('error', 'Tidak ditemukan', 'Data gudang tidak ada.', 'gudang_index.php');