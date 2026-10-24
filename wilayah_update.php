<?php
include 'config.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: wilayah_index.php');
    exit;
}

$id         = (int)($_POST['id'] ?? 0);
$nama       = trim($_POST['nama'] ?? '');
$kode     = trim($_POST['kode'] ?? '');

$kembali = 'wilayah_edit.php?id=' . $id;

if ($id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'ID wilayah tidak valid.', 'wilayah_index.php');
}
if ($nama === '') {
    flash_dan_pindah('error', 'Gagal', 'Nama wilayah wajib diisi.', $kembali);
}

// Kolom opsional: simpan NULL jika kosong
$kode     = $kode === '' ? null : $kode;

try {
    $stmt = $conn->prepare("UPDATE wilayah SET nama = ?, kode = ? WHERE id = ?");
    $stmt->bind_param('ssi', $nama, $kode, $id);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat diperbarui: ' . $e->getMessage(), $kembali);
}

flash_dan_pindah('success', 'Berhasil', 'Wilayah berhasil diperbarui.', 'wilayah_index.php');