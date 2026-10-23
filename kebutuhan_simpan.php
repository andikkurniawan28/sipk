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

$pelanggan_id = (int)($_POST['pelanggan_id'] ?? 0);
$produk_id    = (int)($_POST['produk_id'] ?? 0);
$qty          = (float)($_POST['qty'] ?? 0);
$keterangan   = trim($_POST['keterangan'] ?? '');

if ($pelanggan_id <= 0 || $produk_id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'Pelanggan dan Produk wajib dipilih.', 'kebutuhan_tambah.php');
}

if ($qty < 0) {
    flash_dan_pindah('error', 'Gagal', 'Qty tidak boleh bernilai negatif.', 'kebutuhan_tambah.php');
}

// Kolom opsional: simpan NULL jika kosong
$keterangan = $keterangan === '' ? null : $keterangan;

try {
    $stmt = $conn->prepare("INSERT INTO kebutuhan (pelanggan_id, produk_id, qty, keterangan) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('iids', $pelanggan_id, $produk_id, $qty, $keterangan);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat disimpan: ' . $e->getMessage(), 'kebutuhan_tambah.php');
}

flash_dan_pindah('success', 'Berhasil', 'Kebutuhan pelanggan berhasil ditambahkan.', 'kebutuhan_index.php');