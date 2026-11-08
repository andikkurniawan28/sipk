<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: penyesuaian_stok_index.php');
    exit;
}

$kode        = trim($_POST['kode'] ?? '');
$tanggal     = trim($_POST['tanggal'] ?? '');
$gudang_id   = (int)($_POST['gudang_id'] ?? 0);
$jenis       = trim($_POST['jenis'] ?? ''); // 'tambah' atau 'kurang'
$keterangan  = trim($_POST['keterangan'] ?? '');
$grand_total = (float)str_replace(['.', ','], ['', '.'], $_POST['grand_total'] ?? 0);
$user_id     = $_SESSION['sipk-user_id'];

$produk_ids  = $_POST['produk_id'] ?? [];
$qtys        = $_POST['qty'] ?? [];
$hargas      = $_POST['harga'] ?? [];

if (empty($kode) || empty($tanggal) || $gudang_id <= 0 || !in_array($jenis, ['tambah', 'kurang']) || empty($produk_ids)) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Data tidak lengkap.'];
    header('Location: penyesuaian_stok_tambah.php');
    exit;
}

$conn->begin_transaction();

try {
    // 1. Simpan Header Penyesuaian Stok langsung dengan grand_total dari frontend
    $stmt = $conn->prepare("INSERT INTO penyesuaian_stok (tanggal, kode, gudang_id, user_id, jenis, keterangan, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssiissd', $tanggal, $kode, $gudang_id, $user_id, $jenis, $keterangan, $grand_total);
    $stmt->execute();
    $penyesuaian_id = $stmt->insert_id;
    $stmt->close();

    // 2. Simpan Detail & Mutasi Stok
    for ($i = 0; $i < count($produk_ids); $i++) {
        $produk_id = (int)$produk_ids[$i];
        $qty       = (float)$qtys[$i];
        $harga     = (float)$hargas[$i];
        
        if ($produk_id <= 0 || $qty <= 0) continue;

        $total = $qty * $harga;

        // Insert Detail
        $stmtDet = $conn->prepare("INSERT INTO penyesuaian_stok_detail (penyesuaian_stok_id, produk_id, qty, harga, total) VALUES (?, ?, ?, ?, ?)");
        $stmtDet->bind_param('iiddd', $penyesuaian_id, $produk_id, $qty, $harga, $total);
        $stmtDet->execute();
        $stmtDet->close();

        // Insert Mutasi Stok
        $stmtMutasi = $conn->prepare("INSERT INTO mutasi_stok (penyesuaian_stok_id, tanggal, keterangan, gudang_id, produk_id, masuk, keluar, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $ket = "Penyesuaian Stok (" . ucfirst($jenis) . "): " . $kode;
        
        $masuk  = ($jenis === 'tambah') ? $qty : 0;
        $keluar = ($jenis === 'kurang') ? $qty : 0;

        $stmtMutasi->bind_param('issiiddi', $penyesuaian_id, $tanggal, $ket, $gudang_id, $produk_id, $masuk, $keluar, $user_id);
        $stmtMutasi->execute();
        $stmtMutasi->close();
    }

    // 3. Catat ke Buku Besar (Double-Entry Accounting) menggunakan $grand_total dari frontend
    $ketBB = "Penyesuaian Stok - " . $kode;
    $akunPersediaanId = 14; // Persediaan Barang Jadi

    $stmtBB1 = $conn->prepare("INSERT INTO buku_besar (tanggal, penyesuaian_stok_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtBB2 = $conn->prepare("INSERT INTO buku_besar (tanggal, penyesuaian_stok_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $nol = 0.0;

    if ($jenis === 'tambah') {
        // Debit: Persediaan Barang Jadi (Bertambah)
        $stmtBB1->bind_param('siiisdd', $tanggal, $penyesuaian_id, $akunPersediaanId, $user_id, $ketBB, $grand_total, $nol);
        $stmtBB1->execute();

        // Kredit: Pendapatan Selisih Stok (Bertambah)
        $akunPendapatanId = 22; // Pendapatan Selisih Stok
        $stmtBB2->bind_param('siiisdd', $tanggal, $penyesuaian_id, $akunPendapatanId, $user_id, $ketBB, $nol, $grand_total);
        $stmtBB2->execute();
    } else {
        // Debit: Beban Selisih Stok / Kerugian Persediaan (Bertambah)
        $akunBebanId = 21; // Beban Selisih Stok
        $stmtBB1->bind_param('siiisdd', $tanggal, $penyesuaian_id, $akunBebanId, $user_id, $ketBB, $grand_total, $nol);
        $stmtBB1->execute();

        // Kredit: Persediaan Barang Jadi (Berkurang)
        $stmtBB2->bind_param('siiisdd', $tanggal, $penyesuaian_id, $akunPersediaanId, $user_id, $ketBB, $nol, $grand_total);
        $stmtBB2->execute();
    }

    $stmtBB1->close();
    $stmtBB2->close();

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data penyesuaian stok berhasil disimpan.'];
    header('Location: penyesuaian_stok_index.php');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Kesalahan Sistem', 'text' => $e->getMessage()];
    header('Location: penyesuaian_stok_tambah.php');
    exit;
}