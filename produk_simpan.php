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

$kode       = trim($_POST['kode'] ?? '');
$nama       = trim($_POST['nama'] ?? '');
$satuan     = trim($_POST['satuan'] ?? '');
$harga_beli = $_POST['harga_beli'] ?? '';
$harga_jual = $_POST['harga_jual'] ?? '';
$harga_beli = (float)$harga_beli;
$harga_jual = (float)$harga_jual;

try {
    $stmt = $conn->prepare("INSERT INTO produk (kode, nama, satuan, harga_beli, harga_jual) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('sssdd', $kode, $nama, $satuan, $harga_beli, $harga_jual);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat disimpan: ' . $e->getMessage(), 'produk_tambah.php');
}

flash_dan_pindah('success', 'Berhasil', 'Produk berhasil ditambahkan.', 'produk_index.php');