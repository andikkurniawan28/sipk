<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: preparasi_index.php');
    exit;
}

$tanggal    = trim($_POST['tanggal'] ?? '');
$kode       = trim($_POST['kode'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$user_id    = (int)($_SESSION['sipk-user_id'] ?? 0);

$produkIds  = $_POST['produk_id'] ?? [];
$qtys       = $_POST['qty'] ?? [];

if ($tanggal === '' || $kode === '') {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Tanggal dan Kode wajib diisi.'];
    header('Location: preparasi_tambah.php');
    exit;
}

if (empty($produkIds)) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Item barang preparasi tidak boleh kosong.'];
    header('Location: preparasi_tambah.php');
    exit;
}

$conn->begin_transaction();

try {
    // Simpan Header Preparasi
    $stmt = $conn->prepare("INSERT INTO preparasi (tanggal, kode, keterangan, user_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('sssi', $tanggal, $kode, $keterangan, $user_id);
    $stmt->execute();
    $preparasi_id = $stmt->insert_id;
    $stmt->close();

    // Simpan Detail Preparasi
    $stmtDetail = $conn->prepare("INSERT INTO preparasi_detail (preparasi_id, produk_id, qty) VALUES (?, ?, ?)");

    for ($i = 0; $i < count($produkIds); $i++) {
        $pId = (int)$produkIds[$i];
        $qty = (float)$qtys[$i];

        if ($pId <= 0 || $qty <= 0) {
            throw new Exception("Terdapat data produk atau kuantitas barang yang tidak valid.");
        }

        $stmtDetail->bind_param('iid', $preparasi_id, $pId, $qty);
        $stmtDetail->execute();
    }
    $stmtDetail->close();

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data preparasi berhasil disimpan.'];
    header('Location: preparasi_index.php');
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Penyimpanan gagal: ' . $e->getMessage()];
    header('Location: preparasi_tambah.php');
    exit;
}