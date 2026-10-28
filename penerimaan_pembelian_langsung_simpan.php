<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: penerimaan_pembelian_langsung_index.php');
    exit;
}

$tanggal              = trim($_POST['tanggal'] ?? '');
$kode                 = trim($_POST['kode'] ?? '');
$pembelian_langsung_id = (int)($_POST['pembelian_langsung_id'] ?? 0);
$gudang_id            = (int)($_POST['gudang_id'] ?? 0);
$user_id              = 1; // Sesuaikan session login user

if ($pembelian_langsung_id <= 0 || $gudang_id <= 0) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Data formulir tidak lengkap.'];
    header('Location: penerimaan_pembelian_langsung_tambah.php');
    exit;
}

// Ambil data dari inputan form yang sudah disesuaikan user (qty & total terbaru)
$produk_ids = $_POST['produk_id'] ?? [];
$qtys       = $_POST['qty'] ?? [];
$totals     = $_POST['total'] ?? [];

if (empty($produk_ids)) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Item produk penerimaan tidak ditemukan.'];
    header('Location: penerimaan_pembelian_langsung_tambah.php');
    exit;
}

// Ambil harga asli dari database berdasarkan pembelian_langsung_id & produk_id untuk keamanan validasi
$stmtHarga = $conn->prepare("SELECT produk_id, harga FROM pembelian_langsung_detail WHERE pembelian_langsung_id = ?");
$stmtHarga->bind_param('i', $pembelian_langsung_id);
$stmtHarga->execute();
$resHarga = $stmtHarga->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtHarga->close();

$mapHarga = [];
foreach ($resHarga as $h) {
    $mapHarga[$h['produk_id']] = (float)$h['harga'];
}

$items = [];
$totalPenerimaan = 0;

for ($i = 0; $i < count($produk_ids); $i++) {
    $prodId = (int)$produk_ids[$i];
    $qty    = (float)($qtys[$i] ?? 0);
    $harga  = $mapHarga[$prodId] ?? 0;
    $total  = $qty * $harga; // Hitung ulang total berdasarkan qty terbaru di form

    $items[] = [
        'produk_id' => $prodId,
        'qty'       => $qty,
        'harga'     => $harga,
        'total'     => $total
    ];

    $totalPenerimaan += $total;
}

// Ambil kode pembelian untuk keterangan
$stmtPl = $conn->prepare("SELECT kode FROM pembelian_langsung WHERE id = ? LIMIT 1");
$stmtPl->bind_param('i', $pembelian_langsung_id);
$stmtPl->execute();
$resPl = $stmtPl->get_result()->fetch_assoc();
$stmtPl->close();
$kodePembelian = $resPl['kode'] ?? '';

// Keterangan khusus buku besar & mutasi stok
$keteranganTransaksi = "Diterima {$kode} dari Pembelian Langsung {$kodePembelian}";

// ID Akun sesuai Chart of Accounts Anda
$akunPersediaanId      = 4; // Persediaan Bahan Baku (Debit)
$akunPersediaanBelumId = 3; // Persediaan Bahan Baku Belum Diterima (Kredit)

$conn->begin_transaction();
try {
    // 1. Simpan Header Penerimaan dengan total terbaru
    $stmtIns = $conn->prepare("INSERT INTO penerimaan_pembelian_langsung (tanggal, kode, pembelian_langsung_id, gudang_id, user_id, total) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtIns->bind_param('ssiiid', $tanggal, $kode, $pembelian_langsung_id, $gudang_id, $user_id, $totalPenerimaan);
    $stmtIns->execute();
    $penerimaan_id = $conn->insert_id;
    $stmtIns->close();

    // 2. Simpan Detail Penerimaan (menggunakan qty dan total terbaru dari form)
    $n = count($items);
    $sqlDet = "INSERT INTO penerimaan_pembelian_langsung_detail (penerimaan_pembelian_langsung_id, produk_id, qty, harga, total) VALUES " . implode(',', array_fill(0, $n, '(?,?,?,?,?)'));
    $paramsDet = [];
    foreach ($items as $it) {
        array_push($paramsDet, $penerimaan_id, $it['produk_id'], $it['qty'], $it['harga'], $it['total']);
    }
    $stmtDetIns = $conn->prepare($sqlDet);
    $stmtDetIns->bind_param(str_repeat('iiddd', $n), ...$paramsDet);
    $stmtDetIns->execute();
    $stmtDetIns->close();

    // 3. Simpan ke Buku Besar (Debit: Persediaan Bahan Baku, Kredit: Persediaan Bahan Baku Belum Diterima)
    $stmtBB = $conn->prepare("INSERT INTO buku_besar (tanggal, penerimaan_pembelian_langsung_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $zero = 0.0;

    // Debit Akun 4
    $stmtBB->bind_param('siiisdd', $tanggal, $penerimaan_id, $akunPersediaanId, $user_id, $keteranganTransaksi, $totalPenerimaan, $zero);
    $stmtBB->execute();

    // Kredit Akun 3
    $stmtBB->bind_param('siiisdd', $tanggal, $penerimaan_id, $akunPersediaanBelumId, $user_id, $keteranganTransaksi, $zero, $totalPenerimaan);
    $stmtBB->execute();
    $stmtBB->close();

    // 4. Simpan ke Mutasi Stok (Masuk) sesuai qty terbaru
    $stmtMS = $conn->prepare("INSERT INTO mutasi_stok (penerimaan_pembelian_langsung_id, tanggal, keterangan, gudang_id, produk_id, masuk, keluar, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $keluar = 0.0;
    foreach ($items as $it) {
        $stmtMS->bind_param('issiiddd', $penerimaan_id, $tanggal, $keteranganTransaksi, $gudang_id, $it['produk_id'], $it['qty'], $keluar, $user_id);
        $stmtMS->execute();
    }
    $stmtMS->close();

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data penerimaan pembelian langsung berhasil disimpan.'];
    header('Location: penerimaan_pembelian_langsung_index.php');
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()];
    header('Location: penerimaan_pembelian_langsung_tambah.php');
    exit;
}