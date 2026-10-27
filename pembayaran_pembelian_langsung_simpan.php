<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pembayaran_pembelian_langsung_index.php');
    exit;
}

$tanggal                         = trim($_POST['tanggal'] ?? '');
$kode                            = trim($_POST['kode'] ?? '');
$penerimaan_pembelian_langsung_id = (int)($_POST['penerimaan_pembelian_langsung_id'] ?? 0);
$akun_id                         = (int)($_POST['akun_id'] ?? 0);
$user_id                         = 1; // Sesuaikan dengan session user login

if ($penerimaan_pembelian_langsung_id <= 0 || $akun_id <= 0) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Data formulir pembayaran tidak lengkap.'];
    header('Location: pembayaran_pembelian_langsung_tambah.php');
    exit;
}

// Hitung ulang total berdasarkan item penerimaan (untuk validasi keamanan backend)
$stmtCalc = $conn->prepare("
    SELECT SUM(ppld.qty * pld.harga) AS total_penerimaan, ppl.kode AS kode_penerimaan, pl.kode AS kode_pembelian
    FROM penerimaan_pembelian_langsung ppl
    JOIN penerimaan_pembelian_langsung_detail ppld ON ppld.penerimaan_pembelian_langsung_id = ppl.id
    JOIN pembelian_langsung_detail pld ON pld.pembelian_langsung_id = ppl.pembelian_langsung_id AND pld.produk_id = ppld.produk_id
    JOIN pembelian_langsung pl ON pl.id = ppl.pembelian_langsung_id
    WHERE ppl.id = ?
");
$stmtCalc->bind_param('i', $penerimaan_pembelian_langsung_id);
$stmtCalc->execute();
$resCalc = $stmtCalc->get_result()->fetch_assoc();
$stmtCalc->close();

$total = (float)($resCalc['total_penerimaan'] ?? 0);
$kodePenerimaan = $resCalc['kode_penerimaan'] ?? '';
$kodePembelian = $resCalc['kode_pembelian'] ?? '';

if ($total <= 0) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Nilai total pembayaran tidak valid.'];
    header('Location: pembayaran_pembelian_langsung_tambah.php');
    exit;
}

$keteranganTransaksi = "Pembayaran Pembelian Langsung Ref. Penerimaan {$kodePenerimaan} ({$kodePembelian}) melalui {$kode}";

// ID Akun Hutang Usaha / Persediaan Bahan Baku Belum Diterima (Sesuaikan dengan CoA Anda)
$akunHutangId = 5; // Debit (Mengurangi Hutang / Kewajiban)
// Akun Kas/Bank ($akun_id) akan di-Kredit

$conn->begin_transaction();
try {
    // 1. Simpan Header Pembayaran dengan foreign key penerimaan
    $stmtIns = $conn->prepare("INSERT INTO pembayaran_pembelian_langsung (tanggal, kode, penerimaan_pembelian_langsung_id, user_id, akun_id, total) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtIns->bind_param('ssiiid', $tanggal, $kode, $penerimaan_pembelian_langsung_id, $user_id, $akun_id, $total);
    $stmtIns->execute();
    $pembayaran_id = $conn->insert_id;
    $stmtIns->close();

    // 2. Simpan ke Buku Besar
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