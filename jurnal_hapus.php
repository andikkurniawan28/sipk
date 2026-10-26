<?php
include 'config.php';

// Ambil ID dari parameter GET
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash'] = [
        'icon'  => 'error',
        'title' => 'Gagal',
        'text'  => 'ID jurnal tidak valid.'
    ];
    header('Location: jurnal_index.php');
    exit;
}

// Mulai transaksi database
$conn->begin_transaction();

try {
    // 1. Hapus terlebih dahulu data terkait di buku_besar (jika ada relasi jurnal_id)
    $stmtBB = $conn->prepare("DELETE FROM buku_besar WHERE jurnal_id = ?");
    $stmtBB->bind_param('i', $id);
    $stmtBB->execute();
    $stmtBB->close();

    // 2. Hapus data di jurnal_detail
    $stmtDetail = $conn->prepare("DELETE FROM jurnal_detail WHERE jurnal_id = ?");
    $stmtDetail->bind_param('i', $id);
    $stmtDetail->execute();
    $stmtDetail->close();

    // 3. Hapus data utama di tabel jurnal
    $stmtJurnal = $conn->prepare("DELETE FROM jurnal WHERE id = ?");
    $stmtJurnal->bind_param('i', $id);
    $stmtJurnal->execute();
    $stmtJurnal->close();

    // Commit transaksi jika semuanya berhasil
    $conn->commit();

    $_SESSION['flash'] = [
        'icon'  => 'success',
        'title' => 'Berhasil',
        'text'  => 'Data jurnal beserta rinciannya berhasil dihapus.'
    ];

} catch (Throwable $e) {
    // Rollback jika terjadi kesalahan pada database
    $conn->rollback();

    $_SESSION['flash'] = [
        'icon'  => 'error',
        'title' => 'Gagal Menghapus',
        'text'  => 'Terjadi kesalahan sistem: ' . $e->getMessage()
    ];
}

// Redirect ke halaman index jurnal
header('Location: jurnal_index.php');
exit;