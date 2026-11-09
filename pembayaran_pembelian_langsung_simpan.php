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
$total                 = round((float)($_POST['total'] ?? 0), 2);
$user_id               = $_SESSION['sipk-user_id'];

if (empty($tanggal) || empty($kode) || $pembelian_langsung_id <= 0 || $akun_id <= 0 || $total <= 0) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Data formulir pembayaran tidak lengkap.'];
    header('Location: pembayaran_pembelian_langsung_tambah.php');
    exit;
}

$akunHutangId = 5; // 201.01 Hutang Pemasok (Debit: mengurangi hutang)
// Akun Kas/Bank ($akun_id) di-Kredit

$conn->begin_transaction();
try {
    // 1. Kunci baris faktur agar dua pembayaran bersamaan tidak sama-sama lolos
    $stmtLock = $conn->prepare("SELECT kode, grand_total FROM pembelian_langsung WHERE id = ? FOR UPDATE");
    $stmtLock->bind_param('i', $pembelian_langsung_id);
    $stmtLock->execute();
    $faktur = $stmtLock->get_result()->fetch_assoc();
    $stmtLock->close();

    if (!$faktur) {
        throw new Exception('Faktur pembelian tidak ditemukan.');
    }

    // 2. Pastikan akun yang dipilih memang akun kas/bank
    $stmtAkun = $conn->prepare("SELECT id FROM akun WHERE id = ? AND kelompok = 'Aset' AND kode LIKE '101.%'");
    $stmtAkun->bind_param('i', $akun_id);
    $stmtAkun->execute();
    $akunValid = $stmtAkun->get_result()->fetch_assoc();
    $stmtAkun->close();
    if (!$akunValid) {
        throw new Exception('Akun kas/bank tidak valid.');
    }

    // 3. Hitung sisa tagihan terbaru: grand_total - total pembayaran faktur ini
    $tagihan = round((float)$faktur['grand_total'], 2);

    $stmtBayar = $conn->prepare("SELECT COALESCE(SUM(total), 0) AS dibayar FROM pembayaran_pembelian_langsung WHERE pembelian_langsung_id = ?");
    $stmtBayar->bind_param('i', $pembelian_langsung_id);
    $stmtBayar->execute();
    $dibayar = round((float)$stmtBayar->get_result()->fetch_assoc()['dibayar'], 2);
    $stmtBayar->close();

    $sisa = round($tagihan - $dibayar, 2);

    if ($tagihan <= 0) {
        throw new Exception('Nilai tagihan faktur tidak valid.');
    }
    if ($sisa <= 0) {
        throw new Exception('Faktur ini sudah lunas.');
    }
    if ($total > $sisa) {
        throw new Exception('Jumlah pembayaran (' . number_format($total, 2, ',', '.') . ') melebihi sisa tagihan (' . number_format($sisa, 2, ',', '.') . ').');
    }

    $keteranganTransaksi = "Pembayaran Pembelian Langsung {$faktur['kode']} melalui {$kode}";

    // 4. Header pembayaran
    $stmtIns = $conn->prepare("INSERT INTO pembayaran_pembelian_langsung (tanggal, kode, pembelian_langsung_id, user_id, akun_id, total) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtIns->bind_param('ssiiid', $tanggal, $kode, $pembelian_langsung_id, $user_id, $akun_id, $total);
    $stmtIns->execute();
    $pembayaran_id = $conn->insert_id;
    $stmtIns->close();

    // 5. Buku besar: Dr Hutang Pemasok / Cr Kas-Bank
    $stmtBB = $conn->prepare("INSERT INTO buku_besar (tanggal, pembayaran_pembelian_langsung_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $zero = 0.0;

    $stmtBB->bind_param('siiisdd', $tanggal, $pembayaran_id, $akunHutangId, $user_id, $keteranganTransaksi, $total, $zero);
    $stmtBB->execute();

    $stmtBB->bind_param('siiisdd', $tanggal, $pembayaran_id, $akun_id, $user_id, $keteranganTransaksi, $zero, $total);
    $stmtBB->execute();
    $stmtBB->close();

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data pembayaran pembelian langsung berhasil disimpan.'];
    header('Location: pembayaran_pembelian_langsung_index.php');
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()];
    header('Location: pembayaran_pembelian_langsung_tambah.php');
    exit;
}