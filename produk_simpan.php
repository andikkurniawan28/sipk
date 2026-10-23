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

$nama       = trim($_POST['nama'] ?? '');
$satuan     = trim($_POST['satuan'] ?? '');
$harga_beli = $_POST['harga_beli'] ?? '';
$harga_jual = $_POST['harga_jual'] ?? '';

if ($nama === '' || $satuan === '') {
    flash_dan_pindah('error', 'Gagal', 'Nama dan satuan wajib diisi.', 'produk_tambah.php');
}
if (mb_strlen($nama) > 100 || mb_strlen($satuan) > 20) {
    flash_dan_pindah('error', 'Gagal', 'Nama maksimal 100 karakter dan satuan maksimal 20 karakter.', 'produk_tambah.php');
}
if (!is_numeric($harga_beli) || !is_numeric($harga_jual) || $harga_beli < 0 || $harga_jual < 0) {
    flash_dan_pindah('error', 'Gagal', 'Harga harus berupa angka dan tidak boleh negatif.', 'produk_tambah.php');
}

$harga_beli = (float)$harga_beli;
$harga_jual = (float)$harga_jual;

try {
    $stmt = $conn->prepare("INSERT INTO produk (nama, satuan, harga_beli, harga_jual) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssdd', $nama, $satuan, $harga_beli, $harga_jual);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat disimpan: ' . $e->getMessage(), 'produk_tambah.php');
}

flash_dan_pindah('success', 'Berhasil', 'Produk berhasil ditambahkan.', 'produk_index.php');