<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pembayaran_pembelian_langsung_index.php');
    exit;
}

$tanggal               = trim($_POST['tanggal'] ?? '');
$kode                  = trim($_POST['kode'] ?? '');
$pembelian_langsung_id = (int)($_POST['pembelian_langsung_id'] ?? 0);
$akun_id               = (int)($_POST['akun_id'] ?? 0);
$total                 = (float)($_POST['total'] ?? 0);
$user_id               = 1; // Sesuaikan dengan session user login Anda

if ($pembelian_langsung_id <= 0 || $akun_id <= 0 || $total <= 0) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Data formulir pembayaran tidak lengkap.'];
    header('Location: pembayaran_pembelian_langsung_tambah.php');
    exit;
}

// Ambil kode pembelian untuk keterangan
$stmtPl = $conn->prepare("SELECT kode FROM pembelian_langsung WHERE id = ? LIMIT 1");
$stmtPl->bind_param('i', $pembelian_langsung_id);
$stmtPl->execute();
$resPl = $stmtPl->get_result()->fetch_assoc();
$stmtPl->close();
$kodePembelian = $resPl['kode'] ?? '';

$keteranganTransaksi = "Pembayaran Pembelian Langsung {$kodePembelian} melalui {$kode}";

// ID Akun Hutang Usaha / Persediaan Bahan Baku Belum Diterima (Sesuaikan dengan CoA Anda)
$akunHutangId = 5; // Contoh ID Akun Hutang / Persediaan Belum Diterima (Debit)
// Akun Kas/Bank yang dipilih user ($akun_id) akan di-Kredit

$conn->begin_transaction();
try {
    // 1. Simpan Header Pembayaran
    $stmtIns = $conn->prepare("INSERT INTO pembayaran_pembelian_langsung (tanggal, kode, pembelian_langsung_id, user_id, akun_id, total) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtIns->bind_param('ssiiid', $tanggal, $kode, $pembelian_langsung_id, $user_id, $akun_id, $total);
    $stmtIns->execute();
    $pembayaran_id = $conn->insert_id;
    $stmtIns->close();

    // 2. Simpan ke Buku Besar 
    // Debit: Akun Hutang/Kewajiban ($akunHutangId)
    // Kredit: Akun Kas/Bank yang dipilih ($akun_id)
    $stmtBB = $conn->prepare("INSERT INTO buku_besar (tanggal, pembayaran_pembelian_langsung_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $zero = 0.0;

    // Debit Akun Hutang
    $stmtBB->bind_param('siiisdd', $tanggal, $pembayaran_id, $akunHutangId, $user_id, $keteranganTransaksi, $total, $zero);
    $stmtBB->execute();

    // Kredit Akun Kas/Bank
    $stmtBB->bind_param('siiisdd', $tanggal, $pembayaran_id, $akun_id, $user_id, $keteranganTransaksi, $zero, $total);
    $stmtBB->execute();
    $stmtBB->close();

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Pembayaran pembelian langsung berhasil disimpan.'];
    header('Location: pembayaran_pembelian_langsung_index.php');
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()];
    header('Location: pembayaran_pembelian_langsung_tambah.php');
    exit;
}