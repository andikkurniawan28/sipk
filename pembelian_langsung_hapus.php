<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM pembelian_langsung_detail WHERE pembelian_langsung_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM pembelian_langsung WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data pembelian berhasil dihapus.'];
    } catch (Throwable $e) {
        $conn->rollback();
        $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()];
    }
}

header('Location: pembelian_langsung_index.php');
exit;