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
    flash_dan_pindah('error', 'Gagal', 'ID kebutuhan tidak valid.', 'kebutuhan_index.php');
}

try {
    $stmt = $conn->prepare("DELETE FROM kebutuhan WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat dihapus: ' . $e->getMessage(), 'kebutuhan_index.php');
}

flash_dan_pindah('success', 'Berhasil', 'Kebutuhan pelanggan berhasil dihapus.', 'kebutuhan_index.php');