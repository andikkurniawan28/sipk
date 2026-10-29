<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pembayaran_penjualan_index.php');
    exit;
}

$tanggal      = trim($_POST['tanggal'] ?? '');
$kode         = trim($_POST['kode'] ?? '');
$penjualan_id = (int)($_POST['penjualan_id'] ?? 0);
$akun_id      = (int)($_POST['akun_id'] ?? 0);
$total        = (float)($_POST['total'] ?? 0);
$user_id      = (int)($_SESSION['user_id'] ?? 1);

if ($penjualan_id <= 0 || $akun_id <= 0 || $total <= 0) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Data pembayaran tidak lengkap.'];
    header('Location: pembayaran_penjualan_tambah.php');
    exit;
}

// ID Akun Buku Besar
$akunPiutangId = 16; // Piutang Pelanggan

$conn->begin_transaction();
try {
    // 1. Simpan pembayaran_penjualan
    $stmt = $conn->prepare("INSERT INTO pembayaran_penjualan (tanggal, kode, penjualan_id, user_id, akun_id, total) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssiiid', $tanggal, $kode, $penjualan_id, $user_id, $akun_id, $total);
    $stmt->execute();
    $pembayaran_id = $conn->insert_id;
    $stmt->close();

    // 2. Posting ke buku besar (Kas/Bank [Debit], Piutang [Kredit])
    $keteranganBB = "Pembayaran Piutang Penjualan - " . $kode;
    $stmtBB = $conn->prepare("INSERT INTO buku_besar (tanggal, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, ?, ?, ?, ?)");

    $nol = 0.0;

    // A. Debit Akun Kas/Bank
    $stmtBB->bind_param('siisdd', $tanggal, $akun_id, $user_id, $keteranganBB, $total, $nol);
    $stmtBB->execute();

    // B. Kredit Piutang Pelanggan
    $stmtBB->bind_param('siisdd', $tanggal, $akunPiutangId, $user_id, $keteranganBB, $nol, $total);
    $stmtBB->execute();
    $stmtBB->close();

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Pembayaran piutang berhasil disimpan.'];
    header('Location: pembayaran_penjualan_index.php');
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()];
    header('Location: pembayaran_penjualan_tambah.php');
    exit;
}