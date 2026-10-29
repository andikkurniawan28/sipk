<?php
include 'config.php';
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $conn->begin_transaction();
    try {
        // Berkat ON DELETE CASCADE pada detail dan mutasi_stok, cukup hapus parent row
        $stmt = $conn->prepare("DELETE FROM pengiriman_produk WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Pengiriman produk berhasil dihapus.'];
    } catch (Throwable $e) {
        $conn->rollback();
        $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()];
    }
}

header('Location: pengiriman_produk_index.php');
exit;