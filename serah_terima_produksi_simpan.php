<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: serah_terima_produksi_index.php');
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
    header('Location: serah_terima_produksi_tambah.php');
    exit;
}

$conn->begin_transaction();

try {
    // 1. Simpan Header Serah Terima Produksi
    $stmt = $conn->prepare("INSERT INTO serah_terima_produksi (tanggal, kode, gudang_id, user_id, total) VALUES (?, ?, ?, ?, 0)");
    $stmt->bind_param('ssii', $tanggal, $kode, $gudang_id, $user_id);
    $stmt->execute();
    $serah_id = $stmt->insert_id;
    $stmt->close();

    $grandTotal = 0;

    // 2. Simpan Detail & Mutasi Stok (Masuk)
    for ($i = 0; $i < count($produk_ids); $i++) {
        $produk_id = (int)$produk_ids[$i];
        $qty       = (float)$qtys[$i];
        $harga     = (float)$hargas[$i];
        $total     = $qty * $harga;
        $grandTotal += $total;

        if ($produk_id <= 0 || $qty <= 0) continue;

        // Insert Detail
        $stmtDet = $conn->prepare("INSERT INTO serah_terima_produksi_detail (serah_terima_produksi_id, produk_id, qty, harga, total) VALUES (?, ?, ?, ?, ?)");
        $stmtDet->bind_param('iiddd', $serah_id, $produk_id, $qty, $harga, $total);
        $stmtDet->execute();
        $stmtDet->close();

        // Insert Mutasi Stok (Masuk)
        $stmtMutasi = $conn->prepare("INSERT INTO mutasi_stok (serah_terima_produksi_id, tanggal, keterangan, gudang_id, produk_id, masuk, keluar, user_id) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
        $ket = "Serah Terima Produksi: " . $kode;
        $stmtMutasi->bind_param('issiidi', $serah_id, $tanggal, $ket, $gudang_id, $produk_id, $qty, $user_id);
        $stmtMutasi->execute();
        $stmtMutasi->close();
    }

    // 3. Update Total Header
    $stmtUpd = $conn->prepare("UPDATE serah_terima_produksi SET total = ? WHERE id = ?");
    $stmtUpd->bind_param('di', $grandTotal, $serah_id);
    $stmtUpd->execute();
    $stmtUpd->close();

    // 4. Catat ke Buku Besar (Double-Entry Accounting)
    // Debit: Persediaan Barang Jadi [Akun ID 14]
    $stmtBB1 = $conn->prepare("INSERT INTO buku_besar (tanggal, serah_terima_produksi_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, 14, ?, ?, ?, 0)");
    $ketBB = "Serah Terima Produksi (Barang Jadi Masuk): " . $kode;
    $stmtBB1->bind_param('siisd', $tanggal, $serah_id, $user_id, $ketBB, $grandTotal);
    $stmtBB1->execute();
    $stmtBB1->close();

    // Kredit: Barang Dalam Proses (WIP) [Akun ID 13]
    $stmtBB2 = $conn->prepare("INSERT INTO buku_besar (tanggal, serah_terima_produksi_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, 13, ?, ?, 0, ?)");
    $stmtBB2->bind_param('siisd', $tanggal, $serah_id, $user_id, $ketBB, $grandTotal);
    $stmtBB2->execute();
    $stmtBB2->close();

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data serah terima produksi dan jurnal akuntansi berhasil disimpan.'];
    header('Location: serah_terima_produksi_index.php');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Kesalahan Sistem', 'text' => $e->getMessage()];
    header('Location: serah_terima_produksi_tambah.php');
    exit;
}