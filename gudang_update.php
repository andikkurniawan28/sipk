<?php
include 'config.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: gudang_index.php');
    exit;
}

$id     = (int)($_POST['id'] ?? 0);
$nama   = trim($_POST['nama'] ?? '');
$alamat = trim($_POST['alamat'] ?? '');

$kembali = 'gudang_edit.php?id=' . $id;

if ($id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'ID gudang tidak valid.', 'gudang_index.php');
}
if ($nama === '') {
    flash_dan_pindah('error', 'Gagal', 'Nama gudang wajib diisi.', $kembali);
}
if (mb_strlen($nama) > 100) {
    flash_dan_pindah('error', 'Gagal', 'Nama maksimal 100 karakter.', $kembali);
}

try {
    $stmt = $conn->prepare("UPDATE gudang SET nama = ?, alamat = ? WHERE id = ?");
    $stmt->bind_param('ssi', $nama, $alamat, $id);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat diperbarui: ' . $e->getMessage(), $kembali);
}

flash_dan_pindah('success', 'Berhasil', 'Gudang berhasil diperbarui.', 'gudang_index.php');