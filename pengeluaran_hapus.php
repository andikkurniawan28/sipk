<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    try {
        // Karena relasi foreign key pada mutasi_stok & pengeluaran_detail menggunakan ON DELETE CASCADE,
        // penghapusan header pengeluaran ini otomatis membersihkan detail dan mutasi stok terkait.
        $stmt = $conn->prepare("DELETE FROM pengeluaran WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data pengeluaran berhasil dihapus.'];
    } catch (Throwable $e) {
        $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Gagal menghapus data: ' . $e->getMessage()];
    }
}

header('Location: pengeluaran_index.php');
exit;