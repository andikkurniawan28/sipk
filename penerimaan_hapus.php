<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    try {
        // Karena relasi foreign key pada mutasi_stok & penerimaan_detail menggunakan ON DELETE CASCADE,
        // penghapusan header penerimaan ini otomatis membersihkan detail dan mutasi stok terkait.
        $stmt = $conn->prepare("DELETE FROM penerimaan WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data penerimaan berhasil dihapus.'];
    } catch (Throwable $e) {
        $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Gagal menghapus data: ' . $e->getMessage()];
    }
}

header('Location: penerimaan_index.php');
exit;