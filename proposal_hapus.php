<?php
include 'config.php';

function flash_dan_pindah(string $icon, string $title, string $text): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: proposal_index.php');
    exit;
}

if (empty($_SESSION['sipk-user_id'])) {
    flash_dan_pindah('error', 'Gagal', 'Sesi berakhir, silakan login ulang.');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'ID proposal tidak valid.');
}

try {
    $conn->begin_transaction();

    // Kunci baris dan cek status: hanya Draft yang boleh dihapus
    $stmt = $conn->prepare('SELECT status FROM proposal WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $conn->rollback();
        flash_dan_pindah('error', 'Gagal', 'Proposal tidak ditemukan.');
    }
    if ($row['status'] !== 'Draft') {
        $conn->rollback();
        flash_dan_pindah('error', 'Gagal', 'Hanya proposal berstatus Draft yang boleh dihapus.');
    }

    // proposal_detail ikut terhapus otomatis (ON DELETE CASCADE),
    // tetapi dihapus manual agar tidak bergantung pada skema
    $stmt = $conn->prepare('DELETE FROM proposal_detail WHERE proposal_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM proposal WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    if ((int)$e->getCode() === 1451) {
        flash_dan_pindah('error', 'Gagal', 'Proposal sudah dipakai oleh data lain (penerimaan), sehingga tidak bisa dihapus.');
    }
    error_log('proposal_hapus: ' . $e->getMessage());
    flash_dan_pindah('error', 'Gagal', 'Data proposal tidak dapat dihapus.');
} catch (Throwable $e) {
    $conn->rollback();
    error_log('proposal_hapus: ' . $e->getMessage());
    flash_dan_pindah('error', 'Gagal', 'Data proposal tidak dapat dihapus.');
}

flash_dan_pindah('success', 'Berhasil', 'Data proposal berhasil dihapus.');