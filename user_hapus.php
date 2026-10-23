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
    flash_dan_pindah('error', 'Gagal', 'ID user tidak valid.', 'user_index.php');
}

try {
    // Pengaman: jangan hapus user terakhir, supaya tidak terkunci dari sistem
    $total = (int)$conn->query("SELECT COUNT(*) AS total FROM `user`")->fetch_assoc()['total'];
    if ($total <= 1) {
        flash_dan_pindah('error', 'Gagal', 'User terakhir tidak boleh dihapus.', 'user_index.php');
    }

    $stmt = $conn->prepare("DELETE FROM `user` WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $terhapus = $stmt->affected_rows;
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'User tidak dapat dihapus, mungkin sudah dipakai di data lain.', 'user_index.php');
}

if ($terhapus > 0) {
    flash_dan_pindah('success', 'Berhasil', 'User berhasil dihapus.', 'user_index.php');
}

flash_dan_pindah('error', 'Tidak ditemukan', 'Data user tidak ada.', 'user_index.php');