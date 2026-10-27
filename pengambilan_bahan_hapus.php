<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'ID pengambilan bahan tidak valid.'];
    header('Location: pengambilan_bahan_index.php');
    exit;
}

$conn->begin_transaction();

try {
    // Karena foreign key sudah menggunakan ON DELETE CASCADE untuk mutasi_stok dan detail, 
    // menghapus header akan otomatis membersihkan seluruh relasi terkait.
    $stmt = $conn->prepare("DELETE FROM pengambilan_bahan WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data pengambilan bahan berhasil dihapus.'];
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Gagal menghapus data: ' . $e->getMessage()];
}

header('Location: pengambilan_bahan_index.php');
exit;