<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM produksi WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Terhapus', 'text' => 'Data produksi berhasil dihapus.'];
    } else {
        $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Gagal menghapus data produksi.'];
    }
    $stmt->close();
}

header('Location: produksi_index.php');
exit;