<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: produksi_index.php');
    exit;
}

$tanggal      = trim($_POST['tanggal'] ?? '');
$kode         = trim($_POST['kode'] ?? '');
$preparasi_id = (int)($_POST['preparasi_id'] ?? 0);
$keterangan   = trim($_POST['keterangan'] ?? '');
$user_id      = (int)($_SESSION['sipk-user_id'] ?? 0);

$produkIds    = $_POST['produk_id'] ?? [];
$qtys         = $_POST['qty'] ?? [];

if ($tanggal === '' || $kode === '' || $preparasi_id <= 0) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Tanggal, Kode, dan Referensi Preparasi wajib diisi dengan benar.'];
    header('Location: produksi_tambah.php');
    exit;
}

if (empty($produkIds)) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Item barang output produksi tidak boleh kosong.'];
    header('Location: produksi_tambah.php');
    exit;
}

$conn->begin_transaction();

try {
    // Simpan Header Produksi (terhubung ke preparasi)
    $stmt = $conn->prepare("INSERT INTO produksi (tanggal, kode, preparasi_id, keterangan, user_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('ssisi', $tanggal, $kode, $preparasi_id, $keterangan, $user_id);
    $stmt->execute();
    $produksi_id = $stmt->insert_id;
    $stmt->close();

    // Simpan Detail Produksi
    $stmtDetail = $conn->prepare("INSERT INTO produksi_detail (produksi_id, produk_id, qty) VALUES (?, ?, ?)");

    for ($i = 0; $i < count($produkIds); $i++) {
        $pId = (int)$produkIds[$i];
        $qty = (float)$qtys[$i];

        if ($pId <= 0 || $qty <= 0) {
            throw new Exception("Terdapat data produk atau kuantitas barang output yang tidak valid.");
        }

        $stmtDetail->bind_param('iid', $produksi_id, $pId, $qty);
        $stmtDetail->execute();
    }
    $stmtDetail->close();

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data produksi berhasil disimpan.'];
    header('Location: produksi_index.php');
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Penyimpanan gagal: ' . $e->getMessage()];
    header('Location: produksi_tambah.php');
    exit;
}