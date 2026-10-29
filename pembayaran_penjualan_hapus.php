<?php
include 'config.php';
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM pembayaran_penjualan WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data pembayaran berhasil dihapus.'];
    } catch (Throwable $e) {
        $conn->rollback();
        $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()];
    }
}

header('Location: pembayaran_penjualan_index.php');
exit;