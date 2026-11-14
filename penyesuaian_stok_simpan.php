<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: penyesuaian_stok_index.php');
    exit;
}

$kode       = trim($_POST['kode'] ?? '');
$tanggal    = trim($_POST['tanggal'] ?? '');
$gudang_id  = (int)($_POST['gudang_id'] ?? 0);
$jenis      = trim($_POST['jenis'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$akunPersediaanId = (int)($_POST['akun_persediaan_id'] ?? 0);
$user_id    = $_SESSION['sipk-user_id'];
$produk_ids = $_POST['produk_id'] ?? [];
$qtys       = $_POST['qty'] ?? [];

if (empty($kode) || empty($tanggal) || $gudang_id <= 0 || !in_array($jenis, ['tambah', 'kurang'], true) || empty($produk_ids) || !is_array($produk_ids)) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Data tidak lengkap.'];
    header('Location: penyesuaian_stok_tambah.php');
    exit;
}

if (empty($kode) || empty($tanggal) || $gudang_id <= 0 || $akunPersediaanId <= 0 || !in_array($jenis, ['tambah', 'kurang'], true) || empty($produk_ids) || !is_array($produk_ids)) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Data tidak lengkap.'];
    header('Location: penyesuaian_stok_tambah.php');
    exit;
}

// Gabungkan produk yang sama, abaikan baris kosong / qty <= 0
$qtyPerProduk = [];
foreach ($produk_ids as $i => $p) {
    $produk_id = (int)$p;
    $qty       = (float)($qtys[$i] ?? 0);
    if ($produk_id <= 0 || $qty <= 0) continue;
    $qtyPerProduk[$produk_id] = ($qtyPerProduk[$produk_id] ?? 0.0) + $qty;
}
ksort($qtyPerProduk); // urutan kunci konsisten, mencegah deadlock

if (empty($qtyPerProduk)) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Item produk tidak ditemukan.'];
    header('Location: penyesuaian_stok_tambah.php');
    exit;
}

// $akunPersediaanId = 14; // 103.04 Persediaan Barang Jadi
$akunPendapatanId = 22; // 403.01 Pendapatan Selisih Stok
$akunBebanId      = 21; // 503.02 Beban Kerugian Persediaan

$conn->begin_transaction();

try {
    // 1. Validasi per produk: harga pokok dari master (dikunci), stok cukup jika jenis kurang
    $stmtProduk = $conn->prepare("SELECT nama, harga_pokok FROM produk WHERE id = ? FOR UPDATE");
    $stmtStok   = $conn->prepare("SELECT COALESCE(SUM(masuk - keluar), 0) AS stok FROM mutasi_stok WHERE produk_id = ? AND gudang_id = ?");

    $items = [];
    $grandTotal = 0.0;

    foreach ($qtyPerProduk as $produk_id => $qty) {
        $stmtProduk->bind_param('i', $produk_id);
        $stmtProduk->execute();
        $produk = $stmtProduk->get_result()->fetch_assoc();

        if (!$produk) {
            throw new Exception('Produk tidak ditemukan (ID produk: ' . $produk_id . ').');
        }

        $harga = (float)$produk['harga_pokok'];
        if ($harga <= 0) {
            throw new Exception('Harga pokok "' . $produk['nama'] . '" belum terisi di master produk.');
        }

        if ($jenis === 'kurang') {
            $stmtStok->bind_param('ii', $produk_id, $gudang_id);
            $stmtStok->execute();
            $stok = (float)$stmtStok->get_result()->fetch_assoc()['stok'];
            if ($qty > $stok + 0.0001) {
                throw new Exception('Stok "' . $produk['nama'] . '" di gudang ini tidak cukup (tersedia ' . $stok . ').');
            }
        }

        $total       = round($qty * $harga, 2);
        $grandTotal += $total;
        $items[]     = ['produk_id' => $produk_id, 'qty' => $qty, 'harga' => $harga, 'total' => $total];
    }
    $stmtProduk->close();
    $stmtStok->close();
    $grandTotal = round($grandTotal, 2);

    // 2. Header
    $stmt = $conn->prepare("INSERT INTO penyesuaian_stok (tanggal, kode, gudang_id, user_id, jenis, keterangan, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssiissd', $tanggal, $kode, $gudang_id, $user_id, $jenis, $keterangan, $grandTotal);
    $stmt->execute();
    $penyesuaian_id = $stmt->insert_id;
    $stmt->close();

    // 3. Detail + mutasi stok
    $stmtDet    = $conn->prepare("INSERT INTO penyesuaian_stok_detail (penyesuaian_stok_id, produk_id, qty, harga, total) VALUES (?, ?, ?, ?, ?)");
    $stmtMutasi = $conn->prepare("INSERT INTO mutasi_stok (penyesuaian_stok_id, tanggal, keterangan, gudang_id, produk_id, masuk, keluar, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $ket = "Penyesuaian Stok (" . ucfirst($jenis) . "): " . $kode;

    foreach ($items as $it) {
        $stmtDet->bind_param('iiddd', $penyesuaian_id, $it['produk_id'], $it['qty'], $it['harga'], $it['total']);
        $stmtDet->execute();

        $masuk  = ($jenis === 'tambah') ? $it['qty'] : 0.0;
        $keluar = ($jenis === 'kurang') ? $it['qty'] : 0.0;
        $stmtMutasi->bind_param('issiiddi', $penyesuaian_id, $tanggal, $ket, $gudang_id, $it['produk_id'], $masuk, $keluar, $user_id);
        $stmtMutasi->execute();
    }
    $stmtDet->close();
    $stmtMutasi->close();

    // 4. Buku besar
    $ketBB  = "Penyesuaian Stok - " . $kode;
    $stmtBB = $conn->prepare("INSERT INTO buku_besar (tanggal, penyesuaian_stok_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $nol = 0.0;

    if ($jenis === 'tambah') {
        // Dr Persediaan / Cr Pendapatan Selisih Stok
        $stmtBB->bind_param('siiisdd', $tanggal, $penyesuaian_id, $akunPersediaanId, $user_id, $ketBB, $grandTotal, $nol);
        $stmtBB->execute();
        $stmtBB->bind_param('siiisdd', $tanggal, $penyesuaian_id, $akunPendapatanId, $user_id, $ketBB, $nol, $grandTotal);
        $stmtBB->execute();
    } else {
        // Dr Beban Kerugian Persediaan / Cr Persediaan
        $stmtBB->bind_param('siiisdd', $tanggal, $penyesuaian_id, $akunBebanId, $user_id, $ketBB, $grandTotal, $nol);
        $stmtBB->execute();
        $stmtBB->bind_param('siiisdd', $tanggal, $penyesuaian_id, $akunPersediaanId, $user_id, $ketBB, $nol, $grandTotal);
        $stmtBB->execute();
    }
    $stmtBB->close();

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data penyesuaian stok berhasil disimpan.'];
    header('Location: penyesuaian_stok_index.php');
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()];
    header('Location: penyesuaian_stok_tambah.php');
    exit;
}