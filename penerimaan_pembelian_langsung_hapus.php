<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $conn->begin_transaction();
    try {
        // Karena relasi foreign key pada tabel detail, buku_besar, dan mutasi_stok 
        // sudah diatur dengan ON DELETE CASCADE, menghapus header penerimaan 
        // akan otomatis menghapus seluruh data turunannya secara bersih.
        $stmt = $conn->prepare("DELETE FROM penerimaan_pembelian_langsung WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data penerimaan berhasil dihapus.'];
    } catch (Throwable $e) {
        $conn->rollback();
        $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()];
    }
}

header('Location: penerimaan_pembelian_langsung_index.php');
exit;