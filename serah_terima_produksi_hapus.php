<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'ID serah terima produksi tidak valid.'];
    header('Location: serah_terima_produksi_index.php');
    exit;
}

$conn->begin_transaction();

try {
    // Karena foreign key menggunakan ON DELETE CASCADE untuk mutasi_stok, buku_besar, dan detail,
    // menghapus header akan otomatis membersihkan seluruh relasi terkait secara bersih.
    $stmt = $conn->prepare("DELETE FROM serah_terima_produksi WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data serah terima produksi berhasil dihapus.'];
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Gagal menghapus data: ' . $e->getMessage()];
}

header('Location: serah_terima_produksi_index.php');
exit;