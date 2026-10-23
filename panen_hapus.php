<?php
include 'config.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'ID panen tidak valid.', 'panen_index.php');
}

try {
    // Hitung detail dulu: semuanya ikut terhapus lewat ON DELETE CASCADE (fk_detail_panen)
    $cek = $conn->prepare("SELECT COUNT(*) AS jumlah FROM panen_detail WHERE panen_id = ?");
    $cek->bind_param('i', $id);
    $cek->execute();
    $jumlahDetail = (int)$cek->get_result()->fetch_assoc()['jumlah'];
    $cek->close();

    $stmt = $conn->prepare("DELETE FROM panen WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $terhapus = $stmt->affected_rows;
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Panen tidak dapat dihapus: ' . $e->getMessage(), 'panen_index.php');
}

if ($terhapus > 0) {
    flash_dan_pindah(
        'success',
        'Berhasil',
        'Panen berhasil dihapus beserta ' . $jumlahDetail . ' item detailnya.',
        'panen_index.php'
    );
}

flash_dan_pindah('error', 'Tidak ditemukan', 'Data panen tidak ada.', 'panen_index.php');