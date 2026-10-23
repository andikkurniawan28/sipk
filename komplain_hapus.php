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
    flash_dan_pindah('error', 'Gagal', 'ID komplain tidak valid.', 'komplain_index.php');
}

try {
    $stmt = $conn->prepare("DELETE FROM komplain WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat dihapus: ' . $e->getMessage(), 'komplain_index.php');
}

flash_dan_pindah('success', 'Berhasil', 'Data komplain berhasil dihapus.', 'komplain_index.php');