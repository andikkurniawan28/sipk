<?php
include 'config.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: kebutuhan_index.php');
    exit;
}

$id           = (int)($_POST['id'] ?? 0);
$pelanggan_id = (int)($_POST['pelanggan_id'] ?? 0);
$produk_id    = (int)($_POST['produk_id'] ?? 0);
$qty          = (float)($_POST['qty'] ?? 0);
$keterangan   = trim($_POST['keterangan'] ?? '');

if ($id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'ID kebutuhan tidak valid.', 'kebutuhan_index.php');
}

if ($pelanggan_id <= 0 || $produk_id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'Pelanggan dan Produk wajib dipilih.', "kebutuhan_edit.php?id=$id");
}

if ($qty < 0) {
    flash_dan_pindah('error', 'Gagal', 'Qty tidak boleh bernilai negatif.', "kebutuhan_edit.php?id=$id");
}

// Kolom opsional: simpan NULL jika kosong
$keterangan = $keterangan === '' ? null : $keterangan;

try {
    $stmt = $conn->prepare("UPDATE kebutuhan SET pelanggan_id = ?, produk_id = ?, qty = ?, keterangan = ? WHERE id = ?");
    $stmt->bind_param('iidsi', $pelanggan_id, $produk_id, $qty, $keterangan, $id);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat diperbarui: ' . $e->getMessage(), "kebutuhan_edit.php?id=$id");
}

flash_dan_pindah('success', 'Berhasil', 'Kebutuhan pelanggan berhasil diperbarui.', 'kebutuhan_index.php');