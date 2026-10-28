<?php
include 'config.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: produk_index.php');
    exit;
}

$id         = (int)($_POST['id'] ?? 0);
$kode       = trim($_POST['kode'] ?? '');
$nama       = trim($_POST['nama'] ?? '');
$satuan     = trim($_POST['satuan'] ?? '');
$harga_beli = $_POST['harga_beli'] ?? '';
$harga_pokok = $_POST['harga_pokok'] ?? '';
$harga_jual = $_POST['harga_jual'] ?? '';
$harga_beli = (float)$harga_beli;
$harga_pokok = (float)$harga_pokok;
$harga_jual = (float)$harga_jual;
$kembali = 'produk_edit.php?id=' . $id;

try {
    $stmt = $conn->prepare("UPDATE produk SET kode = ?, nama = ?, satuan = ?, harga_beli = ?, harga_pokok = ?, harga_jual = ? WHERE id = ?");
    $stmt->bind_param('sssdddi', $kode, $nama, $satuan, $harga_beli, $harga_pokok, $harga_jual, $id);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat diperbarui: ' . $e->getMessage(), $kembali);
}

flash_dan_pindah('success', 'Berhasil', 'Produk berhasil diperbarui.', 'produk_index.php');