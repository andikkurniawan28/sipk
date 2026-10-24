<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM preparasi WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Terhapus', 'text' => 'Data preparasi berhasil dihapus.'];
    } else {
        $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Gagal menghapus data preparasi.'];
    }
    $stmt->close();
}

header('Location: preparasi_index.php');
exit;