<?php
include 'config.php';
require_once 'pengeluaran_helper.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['sipk-old-pengeluaran'] = $_POST;
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pengeluaran_index.php');
    exit;
}

$tanggal      = trim($_POST['tanggal'] ?? '');
$kode         = trim($_POST['kode'] ?? '');
$gudang_id    = (int)($_POST['gudang_id'] ?? 0);
$sumber       = trim($_POST['sumber'] ?? 'preparasi');
$referensi_id = (int)($_POST['referensi_id'] ?? 0);
$user_id      = (int)($_SESSION['sipk-user_id'] ?? 0);

$produkIds    = $_POST['produk_id'] ?? [];
$qtys         = $_POST['qty'] ?? [];

// Validasi input utama (pastikan referensi_id terisi)
if ($tanggal === '' || $kode === '' || $gudang_id <= 0 || $referensi_id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'Semua kolom informasi utama wajib diisi dengan benar.', 'pengeluaran_tambah.php');
}

if (empty($produkIds)) {
    flash_dan_pindah('error', 'Gagal', 'Daftar item barang pengeluaran tidak boleh kosong.', 'pengeluaran_tambah.php');
}

// Petakan referensi_id ke kolom yang sesuai berdasarkan sumber ('preparasi' atau 'penjualan')
$preparasi_id = ($sumber === 'preparasi') ? $referensi_id : null;
$penjualan_id = ($sumber === 'penjualan') ? $referensi_id : null;

if (!$preparasi_id && !$penjualan_id) {
    flash_dan_pindah('error', 'Gagal', 'Sumber referensi tidak valid.', 'pengeluaran_tambah.php');
}

$conn->begin_transaction();

try {
    // 1. Simpan Header Pengeluaran (mendukung preparasi_id dan penjualan_id)
    $stmt = $conn->prepare("INSERT INTO pengeluaran (tanggal, kode, gudang_id, user_id, preparasi_id, penjualan_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssiiii', $tanggal, $kode, $gudang_id, $user_id, $preparasi_id, $penjualan_id);
    $stmt->execute();
    $pengeluaran_id = $stmt->insert_id;
    $stmt->close();

    // 2. Simpan Detail Pengeluaran & Catat Mutasi Stok Keluar
    $stmtDetail = $conn->prepare("INSERT INTO pengeluaran_detail (pengeluaran_id, produk_id, qty) VALUES (?, ?, ?)");
    
    // Urutan kolom: gudang_id, produk_id, pengeluaran_id, tanggal, keterangan, masuk, keluar, user_id
    $stmtMutasi = $conn->prepare("INSERT INTO mutasi_stok (gudang_id, produk_id, pengeluaran_id, tanggal, keterangan, masuk, keluar, user_id) VALUES (?, ?, ?, ?, ?, 0, ?, ?)");

    for ($i = 0; $i < count($produkIds); $i++) {
        $pId = (int)$produkIds[$i];
        $qty = (float)$qtys[$i];

        if ($pId <= 0 || $qty <= 0) {
            throw new Exception("Terdapat data produk atau kuantitas barang yang tidak valid.");
        }

        // Insert Detail
        $stmtDetail->bind_param('iid', $pengeluaran_id, $pId, $qty);
        $stmtDetail->execute();

        // Insert Mutasi Stok Keluar
        $ket = "Pengeluaran Dokumen: " . $kode;
        $stmtMutasi->bind_param('iiissdi', $gudang_id, $pId, $pengeluaran_id, $tanggal, $ket, $qty, $user_id);
        $stmtMutasi->execute();
    }

    $stmtDetail->close();
    $stmtMutasi->close();

    $conn->commit();
    unset($_SESSION['sipk-old-pengeluaran']);
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Pengeluaran berhasil disimpan dan stok gudang diperbarui.'];
    header('Location: pengeluaran_index.php');
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    flash_dan_pindah('error', 'Gagal', 'Penyimpanan gagal: ' . $e->getMessage(), 'pengeluaran_tambah.php');
}