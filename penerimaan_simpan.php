<?php
include 'config.php';
require_once 'penerimaan_helper.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['sipk-old-penerimaan'] = $_POST;
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: penerimaan_index.php');
    exit;
}

$tanggal      = trim($_POST['tanggal'] ?? '');
$kode         = trim($_POST['kode'] ?? '');
$gudang_id    = (int)($_POST['gudang_id'] ?? 0);
$sumber       = trim($_POST['sumber'] ?? 'panen');
$referensi_id = (int)($_POST['referensi_id'] ?? 0);
$user_id      = (int)($_SESSION['sipk-user_id'] ?? 0);

$produkIds    = $_POST['produk_id'] ?? [];
$qtys         = $_POST['qty'] ?? [];

// Validasi input utama (pastikan referensi_id terisi)
if ($tanggal === '' || $kode === '' || $gudang_id <= 0 || $referensi_id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'Semua kolom informasi utama wajib diisi dengan benar.', 'penerimaan_tambah.php');
}

if (empty($produkIds)) {
    flash_dan_pindah('error', 'Gagal', 'Daftar item barang penerimaan tidak boleh kosong.', 'penerimaan_tambah.php');
}

// Karena penerimaan murni bersumber dari panen, petakan referensi_id ke panen_id jika sumbernya 'panen'
$panen_id = ($sumber === 'panen') ? $referensi_id : null;

if (!$panen_id) {
    flash_dan_pindah('error', 'Gagal', 'Sumber referensi panen tidak valid.', 'penerimaan_tambah.php');
}

$conn->begin_transaction();

try {
    // 1. Simpan Header Penerimaan
    $stmt = $conn->prepare("INSERT INTO penerimaan (tanggal, kode, gudang_id, user_id, panen_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('ssiii', $tanggal, $kode, $gudang_id, $user_id, $panen_id);
    $stmt->execute();
    $penerimaan_id = $stmt->insert_id;
    $stmt->close();

    // 2. Simpan Detail Penerimaan & Catat Mutasi Stok Masuk
    $stmtDetail = $conn->prepare("INSERT INTO penerimaan_detail (penerimaan_id, produk_id, qty) VALUES (?, ?, ?)");
    
    // Urutan kolom: gudang_id, produk_id, penerimaan_id, tanggal, keterangan, masuk, keluar, user_id
    $stmtMutasi = $conn->prepare("INSERT INTO mutasi_stok (gudang_id, produk_id, penerimaan_id, tanggal, keterangan, masuk, keluar, user_id) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");

    for ($i = 0; $i < count($produkIds); $i++) {
        $pId = (int)$produkIds[$i];
        $qty = (float)$qtys[$i];

        if ($pId <= 0 || $qty <= 0) {
            throw new Exception("Terdapat data produk atau kuantitas barang yang tidak valid.");
        }

        // Insert Detail
        $stmtDetail->bind_param('iid', $penerimaan_id, $pId, $qty);
        $stmtDetail->execute();

        // Insert Mutasi Stok Masuk
        $ket = "Penerimaan Dokumen: " . $kode;
        $stmtMutasi->bind_param('iiissdi', $gudang_id, $pId, $penerimaan_id, $tanggal, $ket, $qty, $user_id);
        $stmtMutasi->execute();
    }

    $stmtDetail->close();
    $stmtMutasi->close();

    $conn->commit();
    unset($_SESSION['sipk-old-penerimaan']);
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Penerimaan berhasil disimpan dan stok gudang diperbarui.'];
    header('Location: penerimaan_index.php');
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    flash_dan_pindah('error', 'Gagal', 'Penyimpanan gagal: ' . $e->getMessage(), 'penerimaan_tambah.php');
}