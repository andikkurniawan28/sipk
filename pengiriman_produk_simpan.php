<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pengiriman_produk_index.php');
    exit;
}

$tanggal      = trim($_POST['tanggal'] ?? '');
$kode         = trim($_POST['kode'] ?? '');
$penjualan_id = (int)($_POST['penjualan_id'] ?? 0);
$gudang_id    = (int)($_POST['gudang_id'] ?? 0);
$user_id      = (int)($_SESSION['user_id'] ?? 1);

$pid   = $_POST['produk_id'] ?? [];
$qtys  = $_POST['qty'] ?? [];
$hargas= $_POST['harga'] ?? [];

$items = [];
$total_do = 0;

foreach ($pid as $i => $p) {
    $produk_id = (int)$p;
    if ($produk_id <= 0) continue;

    $q = (float)($qtys[$i] ?? 0);
    $h = (float)($hargas[$i] ?? 0);
    $tot = round($q * $h, 2);

    $items[] = ['produk_id' => $produk_id, 'qty' => $q, 'harga' => $h, 'total' => $tot];
    $total_do += $tot;
}

if (empty($items) || $penjualan_id <= 0 || $gudang_id <= 0) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Data pengiriman tidak lengkap.'];
    header('Location: pengiriman_produk_tambah.php');
    exit;
}

$conn->begin_transaction();
try {
    // 1. Simpan Header Pengiriman Produk
    $stmt = $conn->prepare("INSERT INTO pengiriman_produk (tanggal, kode, penjualan_id, gudang_id, user_id, total) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssiiid', $tanggal, $kode, $penjualan_id, $gudang_id, $user_id, $total_do);
    $stmt->execute();
    $pengiriman_id = $conn->insert_id;
    $stmt->close();

    // 2. Simpan Detail Pengiriman Produk
    $n = count($items);
    $sqlDetail = "INSERT INTO pengiriman_produk_detail (pengiriman_produk_id, produk_id, qty, harga, total) VALUES " . implode(',', array_fill(0, $n, '(?,?,?,?,?)'));
    $params = [];
    foreach ($items as $it) {
        array_push($params, $pengiriman_id, $it['produk_id'], $it['qty'], $it['harga'], $it['total']);
    }
    $stmtDetail = $conn->prepare($sqlDetail);
    $stmtDetail->bind_param(str_repeat('iiddd', $n), ...$params);
    $stmtDetail->execute();
    $stmtDetail->close();

    // 3. Catat Mutasi Stok Keluar (Mengurangi Stok Gudang)
    $stmtMutasi = $conn->prepare("INSERT INTO mutasi_stok (pengiriman_produk_id, tanggal, keterangan, gudang_id, produk_id, masuk, keluar, user_id) VALUES (?, ?, ?, ?, ?, 0, ?, ?)");
    $keteranganMutasi = "Pengiriman Produk / Surat Jalan - " . $kode;

    foreach ($items as $it) {
        $stmtMutasi->bind_param('issiiid', $pengiriman_id, $tanggal, $keteranganMutasi, $gudang_id, $it['produk_id'], $it['qty'], $user_id);
        $stmtMutasi->execute();
    }
    $stmtMutasi->close();

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Pengiriman produk berhasil disimpan.'];
    header('Location: pengiriman_produk_index.php');
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()];
    header('Location: pengiriman_produk_tambah.php');
    exit;
}