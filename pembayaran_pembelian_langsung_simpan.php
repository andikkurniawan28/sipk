<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pembayaran_pembelian_langsung_index.php');
    exit;
}

$tanggal                          = trim($_POST['tanggal'] ?? '');
$kode                             = trim($_POST['kode'] ?? '');
$penerimaan_pembelian_langsung_id = (int)($_POST['penerimaan_pembelian_langsung_id'] ?? 0);
$akun_id                          = (int)($_POST['akun_id'] ?? 0);
$total                            = round((float)($_POST['total'] ?? 0), 2);
$user_id                          = $_SESSION['sipk-user_id'];

if (empty($tanggal) || empty($kode) || $penerimaan_pembelian_langsung_id <= 0 || $akun_id <= 0 || $total <= 0) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Data formulir pembayaran tidak lengkap.'];
    header('Location: pembayaran_pembelian_langsung_tambah.php');
    exit;
}

// ID Akun Hutang Usaha / Persediaan Bahan Baku Belum Diterima (Sesuaikan dengan CoA Anda)
$akunHutangId = 5; // Debit (Mengurangi Hutang / Kewajiban)
// Akun Kas/Bank ($akun_id) akan di-Kredit

$conn->begin_transaction();
try {
    // 1. Kunci baris penerimaan agar dua pembayaran bersamaan tidak lolos bersamaan
    $stmtLock = $conn->prepare("SELECT id FROM penerimaan_pembelian_langsung WHERE id = ? FOR UPDATE");
    $stmtLock->bind_param('i', $penerimaan_pembelian_langsung_id);
    $stmtLock->execute();
    $adaPen = $stmtLock->get_result()->fetch_assoc();
    $stmtLock->close();
    if (!$adaPen) {
        throw new Exception('Penerimaan pembelian tidak ditemukan.');
    }

    // 2. Hitung ulang tagihan (qty penerimaan x harga pembelian) dan yang sudah dibayar
    $stmtCalc = $conn->prepare("
        SELECT SUM(ppld.qty * pld.harga) AS total_penerimaan,
               ppl.kode AS kode_penerimaan,
               pl.kode AS kode_pembelian
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

    $tagihan        = round((float)($resCalc['total_penerimaan'] ?? 0), 2);
    $kodePenerimaan = $resCalc['kode_penerimaan'] ?? '';
    $kodePembelian  = $resCalc['kode_pembelian'] ?? '';

    $stmtBayar = $conn->prepare("SELECT COALESCE(SUM(total), 0) AS dibayar FROM pembayaran_pembelian_langsung WHERE penerimaan_pembelian_langsung_id = ?");
    $stmtBayar->bind_param('i', $penerimaan_pembelian_langsung_id);
    $stmtBayar->execute();
    $dibayar = round((float)$stmtBayar->get_result()->fetch_assoc()['dibayar'], 2);
    $stmtBayar->close();

    $sisa = round($tagihan - $dibayar, 2);

    if ($tagihan <= 0) {
        throw new Exception('Nilai tagihan penerimaan tidak valid.');
    }
    if ($sisa <= 0) {
        throw new Exception('Tagihan ini sudah lunas.');
    }
    if ($total > $sisa) {
        throw new Exception('Jumlah pembayaran (' . number_format($total, 2, ',', '.') . ') melebihi sisa tagihan (' . number_format($sisa, 2, ',', '.') . ').');
    }

    $keteranganTransaksi = "Pembayaran Pembelian Langsung Ref. Penerimaan {$kodePenerimaan} ({$kodePembelian}) melalui {$kode}";

    // 3. Simpan Header Pembayaran
    $stmtIns = $conn->prepare("INSERT INTO pembayaran_pembelian_langsung (tanggal, kode, penerimaan_pembelian_langsung_id, user_id, akun_id, total) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtIns->bind_param('ssiiid', $tanggal, $kode, $penerimaan_pembelian_langsung_id, $user_id, $akun_id, $total);
    $stmtIns->execute();
    $pembayaran_id = $conn->insert_id;
    $stmtIns->close();

    // 4. Simpan ke Buku Besar
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
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data pembayaran pembelian langsung berhasil disimpan.'];
    header('Location: pembayaran_pembelian_langsung_index.php');
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()];
    header('Location: pembayaran_pembelian_langsung_tambah.php');
    exit;
}