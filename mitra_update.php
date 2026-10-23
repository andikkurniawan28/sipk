<?php
include 'config.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mitra_index.php');
    exit;
}

$id         = (int)($_POST['id'] ?? 0);
$nama       = trim($_POST['nama'] ?? '');
$alamat     = trim($_POST['alamat'] ?? '');
$telepon    = trim($_POST['telepon'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');

$kembali = 'mitra_edit.php?id=' . $id;

if ($id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'ID mitra tidak valid.', 'mitra_index.php');
}
if ($nama === '') {
    flash_dan_pindah('error', 'Gagal', 'Nama mitra wajib diisi.', $kembali);
}
if (mb_strlen($nama) > 100 || mb_strlen($telepon) > 30) {
    flash_dan_pindah('error', 'Gagal', 'Nama maksimal 100 karakter dan telepon maksimal 30 karakter.', $kembali);
}

// Kolom opsional: simpan NULL jika kosong
$alamat     = $alamat === '' ? null : $alamat;
$telepon    = $telepon === '' ? null : $telepon;
$keterangan = $keterangan === '' ? null : $keterangan;

try {
    $stmt = $conn->prepare("UPDATE mitra SET nama = ?, alamat = ?, telepon = ?, keterangan = ? WHERE id = ?");
    $stmt->bind_param('ssssi', $nama, $alamat, $telepon, $keterangan, $id);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat diperbarui: ' . $e->getMessage(), $kembali);
}

flash_dan_pindah('success', 'Berhasil', 'Mitra berhasil diperbarui.', 'mitra_index.php');