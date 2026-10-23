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
    flash_dan_pindah('error', 'Gagal', 'ID pengolahan tidak valid.', 'pengolahan_index.php');
}

try {
    // Hitung dulu: semuanya ikut terhapus lewat ON DELETE CASCADE (fk_inputpg_pengolahan, fk_outputpg_pengolahan)
    $cek = $conn->prepare("
        SELECT
            (SELECT COUNT(*) FROM input_pengolahan WHERE pengolahan_id = ?) AS jumlah_input,
            (SELECT COUNT(*) FROM output_pengolahan WHERE pengolahan_id = ?) AS jumlah_output
    ");
    $cek->bind_param('ii', $id, $id);
    $cek->execute();
    $jumlah = $cek->get_result()->fetch_assoc();
    $cek->close();

    $stmt = $conn->prepare("DELETE FROM pengolahan WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $terhapus = $stmt->affected_rows;
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Pengolahan tidak dapat dihapus: ' . $e->getMessage(), 'pengolahan_index.php');
}

if ($terhapus > 0) {
    flash_dan_pindah(
        'success',
        'Berhasil',
        'Pengolahan berhasil dihapus beserta ' . (int)$jumlah['jumlah_input'] . ' item input dan '
            . (int)$jumlah['jumlah_output'] . ' item output.',
        'pengolahan_index.php'
    );
}

flash_dan_pindah('error', 'Tidak ditemukan', 'Data pengolahan tidak ada.', 'pengolahan_index.php');