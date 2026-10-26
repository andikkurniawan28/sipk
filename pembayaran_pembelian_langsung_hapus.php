<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $conn->begin_transaction();
    try {
        // Karena relasi foreign key pada tabel buku_besar sudah diatur dengan ON DELETE CASCADE,
        // menghapus header pembayaran akan otomatis menghapus catatan buku besar terkait.
        $stmt = $conn->prepare("DELETE FROM pembayaran_pembelian_langsung WHERE id = ?");
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

header('Location: pembayaran_pembelian_langsung_index.php');
exit;