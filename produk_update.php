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
$nama       = trim($_POST['nama'] ?? '');
$satuan     = trim($_POST['satuan'] ?? '');
$harga_beli = $_POST['harga_beli'] ?? '';
$harga_jual = $_POST['harga_jual'] ?? '';

$kembali = 'produk_edit.php?id=' . $id;

if ($id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'ID produk tidak valid.', 'produk_index.php');
}
if ($nama === '' || $satuan === '') {
    flash_dan_pindah('error', 'Gagal', 'Nama dan satuan wajib diisi.', $kembali);
}
if (mb_strlen($nama) > 100 || mb_strlen($satuan) > 20) {
    flash_dan_pindah('error', 'Gagal', 'Nama maksimal 100 karakter dan satuan maksimal 20 karakter.', $kembali);
}
if (!is_numeric($harga_beli) || !is_numeric($harga_jual) || $harga_beli < 0 || $harga_jual < 0) {
    flash_dan_pindah('error', 'Gagal', 'Harga harus berupa angka dan tidak boleh negatif.', $kembali);
}

$harga_beli = (float)$harga_beli;
$harga_jual = (float)$harga_jual;

try {
    $stmt = $conn->prepare("UPDATE produk SET nama = ?, satuan = ?, harga_beli = ?, harga_jual = ? WHERE id = ?");
    $stmt->bind_param('ssddi', $nama, $satuan, $harga_beli, $harga_jual, $id);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat diperbarui: ' . $e->getMessage(), $kembali);
}

flash_dan_pindah('success', 'Berhasil', 'Produk berhasil diperbarui.', 'produk_index.php');