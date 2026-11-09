<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pengambilan_bahan_index.php');
    exit;
}

$kode       = trim($_POST['kode'] ?? '');
$tanggal    = trim($_POST['tanggal'] ?? '');
$gudang_id  = (int)($_POST['gudang_id'] ?? 0);
$user_id    = $_SESSION['sipk-user_id'];
$produk_ids = $_POST['produk_id'] ?? [];
$qtys       = $_POST['qty'] ?? [];

if (empty($kode) || empty($tanggal) || $gudang_id <= 0 || empty($produk_ids) || !is_array($produk_ids)) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Data tidak lengkap.'];
    header('Location: pengambilan_bahan_tambah.php');
    exit;
}

// Gabungkan baris produk yang sama, abaikan baris kosong / qty <= 0
$items = [];
foreach ($produk_ids as $i => $p) {
    $produk_id = (int)$p;
    $qty       = (float)($qtys[$i] ?? 0);
    if ($produk_id <= 0 || $qty <= 0) continue;
    $items[$produk_id] = ($items[$produk_id] ?? 0.0) + $qty;
}
ksort($items); // urutan kunci konsisten, mencegah deadlock

if (empty($items)) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Item produk tidak ditemukan.'];
    header('Location: pengambilan_bahan_tambah.php');
    exit;
}

$akunWipId        = 13; // Barang Dalam Proses (Debit)
$akunBahanBakuId  = 4;  // Persediaan Bahan Baku (Kredit)

$conn->begin_transaction();

try {
    // 1. Kunci baris produk, ambil harga pokok, dan cek stok gudang
    $stmtPk   = $conn->prepare("SELECT nama, harga_pokok FROM produk WHERE id = ? FOR UPDATE");
    $stmtStok = $conn->prepare("SELECT COALESCE(SUM(masuk - keluar), 0) AS stok FROM mutasi_stok WHERE produk_id = ? AND gudang_id = ?");

    $rows       = [];
    $grandTotal = 0.0;

    foreach ($items as $produk_id => $qty) {
        $stmtPk->bind_param('i', $produk_id);
        $stmtPk->execute();
        $produk = $stmtPk->get_result()->fetch_assoc();

        if (!$produk) {
            throw new Exception('Produk tidak ditemukan (ID produk: ' . $produk_id . ').');
        }

        $harga = (float)$produk['harga_pokok'];
        if ($harga <= 0) {
            throw new Exception('Harga pokok "' . $produk['nama'] . '" belum terisi. Lakukan penerimaan terlebih dahulu.');
        }

        $stmtStok->bind_param('ii', $produk_id, $gudang_id);
        $stmtStok->execute();
        $stok = (float)$stmtStok->get_result()->fetch_assoc()['stok'];

        if ($qty > $stok + 0.0001) {
            throw new Exception('Stok "' . $produk['nama'] . '" di gudang ini tidak cukup (tersedia ' . rtrim(rtrim(number_format($stok, 2, ',', '.'), '0'), ',') . ').');
        }

        $total = round($qty * $harga, 2);
        $grandTotal += $total;
        $rows[] = ['produk_id' => $produk_id, 'qty' => $qty, 'harga' => $harga, 'total' => $total];
    }
    $stmtPk->close();
    $stmtStok->close();
    $grandTotal = round($grandTotal, 2);

    // 2. Header
    $stmt = $conn->prepare("INSERT INTO pengambilan_bahan (tanggal, kode, gudang_id, user_id, total) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('ssiid', $tanggal, $kode, $gudang_id, $user_id, $grandTotal);
    $stmt->execute();
    $pengambilan_id = $stmt->insert_id;
    $stmt->close();

    // 3. Detail + mutasi stok keluar
    $stmtDet = $conn->prepare("INSERT INTO pengambilan_bahan_detail (pengambilan_bahan_id, produk_id, qty, harga, total) VALUES (?, ?, ?, ?, ?)");
    $stmtMutasi = $conn->prepare("INSERT INTO mutasi_stok (pengambilan_bahan_id, tanggal, keterangan, gudang_id, produk_id, masuk, keluar, user_id) VALUES (?, ?, ?, ?, ?, 0, ?, ?)");
    $ket = "Pengambilan Bahan: " . $kode;

    foreach ($rows as $r) {
        $stmtDet->bind_param('iiddd', $pengambilan_id, $r['produk_id'], $r['qty'], $r['harga'], $r['total']);
        $stmtDet->execute();

        $stmtMutasi->bind_param('issiidi', $pengambilan_id, $tanggal, $ket, $gudang_id, $r['produk_id'], $r['qty'], $user_id);
        $stmtMutasi->execute();
    }
    $stmtDet->close();
    $stmtMutasi->close();

    // 4. Buku besar: Dr WIP / Cr Persediaan Bahan Baku
    $ketBB = "Pengambilan Bahan ke Produksi: " . $kode;
    $stmtBB = $conn->prepare("INSERT INTO buku_besar (tanggal, pengambilan_bahan_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $debit  = $grandTotal; $kredit0 = 0.0;
    $stmtBB->bind_param('siiisdd', $tanggal, $pengambilan_id, $akunWipId, $user_id, $ketBB, $debit, $kredit0);
    $stmtBB->execute();

    $debit0 = 0.0; $kredit = $grandTotal;
    $stmtBB->bind_param('siiisdd', $tanggal, $pengambilan_id, $akunBahanBakuId, $user_id, $ketBB, $debit0, $kredit);
    $stmtBB->execute();
    $stmtBB->close();

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data pengambilan bahan berhasil disimpan.'];
    header('Location: pengambilan_bahan_index.php');
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()];
    header('Location: pengambilan_bahan_tambah.php');
    exit;
}