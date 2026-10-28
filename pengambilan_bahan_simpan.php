<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pengambilan_bahan_index.php');
    exit;
}

$kode       = trim($_POST['kode'] ?? '');
$tanggal    = trim($_POST['tanggal'] ?? '');
$gudang_id  = (int)($_POST['gudang_id'] ?? 0);
$user_id    = (int)($_SESSION['user_id'] ?? 1); // Admin aktif
$produk_ids = $_POST['produk_id'] ?? [];
$qtys       = $_POST['qty'] ?? [];
$hargas     = $_POST['harga'] ?? [];

if (empty($kode) || empty($tanggal) || $gudang_id <= 0 || empty($produk_ids)) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Data tidak lengkap.'];
    header('Location: pengambilan_bahan_tambah.php');
    exit;
}

$conn->begin_transaction();

try {
    // 1. Simpan Header Pengambilan Bahan
    $stmt = $conn->prepare("INSERT INTO pengambilan_bahan (tanggal, kode, gudang_id, user_id, total) VALUES (?, ?, ?, ?, 0)");
    $stmt->bind_param('ssii', $tanggal, $kode, $gudang_id, $user_id);
    $stmt->execute();
    $pengambilan_id = $stmt->insert_id;
    $stmt->close();

    $grandTotal = 0;

    // 2. Simpan Detail & Mutasi Stok (Keluar)
    for ($i = 0; $i < count($produk_ids); $i++) {
        $produk_id = (int)$produk_ids[$i];
        $qty       = (float)$qtys[$i];
        $harga     = (float)$hargas[$i];
        $total     = $qty * $harga;
        $grandTotal += $total;

        if ($produk_id <= 0 || $qty <= 0) continue;

        // Insert Detail
        $stmtDet = $conn->prepare("INSERT INTO pengambilan_bahan_detail (pengambilan_bahan_id, produk_id, qty, harga, total) VALUES (?, ?, ?, ?, ?)");
        $stmtDet->bind_param('iiddd', $pengambilan_id, $produk_id, $qty, $harga, $total);
        $stmtDet->execute();
        $stmtDet->close();

        // Insert Mutasi Stok (Keluar)
        $stmtMutasi = $conn->prepare("INSERT INTO mutasi_stok (pengambilan_bahan_id, tanggal, keterangan, gudang_id, produk_id, masuk, keluar, user_id) VALUES (?, ?, ?, ?, ?, 0, ?, ?)");
        $ket = "Pengambilan Bahan: " . $kode;
        $stmtMutasi->bind_param('issiidi', $pengambilan_id, $tanggal, $ket, $gudang_id, $produk_id, $qty, $user_id);
        $stmtMutasi->execute();
        $stmtMutasi->close();
    }

    // 3. Update Total Header
    $stmtUpd = $conn->prepare("UPDATE pengambilan_bahan SET total = ? WHERE id = ?");
    $stmtUpd->bind_param('di', $grandTotal, $pengambilan_id);
    $stmtUpd->execute();
    $stmtUpd->close();

    // 4. Catat ke Buku Besar (Double-Entry Accounting)
    // Debit: Barang Dalam Proses (WIP) [Akun ID 13]
    $stmtBB1 = $conn->prepare("INSERT INTO buku_besar (tanggal, pengambilan_bahan_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, 13, ?, ?, ?, 0)");
    $ketBB = "Pengambilan Bahan ke Produksi: " . $kode;
    $stmtBB1->bind_param('siisd', $tanggal, $pengambilan_id, $user_id, $ketBB, $grandTotal);
    $stmtBB1->execute();
    $stmtBB1->close();

    // Kredit: Persediaan Bahan Baku [Akun ID 4]
    $stmtBB2 = $conn->prepare("INSERT INTO buku_besar (tanggal, pengambilan_bahan_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, 4, ?, ?, 0, ?)");
    $stmtBB2->bind_param('siisd', $tanggal, $pengambilan_id, $user_id, $ketBB, $grandTotal);
    $stmtBB2->execute();
    $stmtBB2->close();

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data pengambilan bahan berhasil disimpan.'];
    header('Location: pengambilan_bahan_index.php');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Kesalahan Sistem', 'text' => $e->getMessage()];
    header('Location: pengambilan_bahan_tambah.php');
    exit;
}