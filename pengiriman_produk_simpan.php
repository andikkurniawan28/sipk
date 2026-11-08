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
$user_id      = $_SESSION['sipk-user_id'];

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

// Mapping ID Akun (HPP dipindah dari penjualan ke pengiriman)
$akunHppId        = 20; // Harga Pokok Penjualan / HPP (Debit)
$akunPersediaanId = 14; // Persediaan Barang Jadi (Kredit)

$conn->begin_transaction();
try {
    // 0. Ambil harga pokok dari penjualan_detail (dikunci saat penjualan dibuat).
    //    Sengaja TIDAK dari POST, supaya nilai HPP tidak bisa dimanipulasi dari browser.
    //    Bila satu produk muncul di beberapa baris, dipakai harga pokok rata-rata tertimbang.
    $stmtHp = $conn->prepare("
        SELECT produk_id, SUM(qty * harga_pokok) / SUM(qty) AS hp
        FROM penjualan_detail
        WHERE penjualan_id = ?
        GROUP BY produk_id
        HAVING SUM(qty) > 0
    ");
    $stmtHp->bind_param('i', $penjualan_id);
    $stmtHp->execute();
    $hpMap = [];
    foreach ($stmtHp->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $hpMap[(int)$r['produk_id']] = (float)$r['hp'];
    }
    $stmtHp->close();

    $total_hpp = 0.0;
    foreach ($items as $it) {
        if (!isset($hpMap[$it['produk_id']])) {
            throw new Exception('Ada produk yang tidak terdapat pada penjualan ini (ID produk: ' . $it['produk_id'] . ').');
        }
        $total_hpp += round($it['qty'] * $hpMap[$it['produk_id']], 2);
    }
    $total_hpp = round($total_hpp, 2);

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
    //    Tipe bind: i s s i i d i  (qty = DECIMAL -> 'd', user_id -> 'i')
    $stmtMutasi = $conn->prepare("INSERT INTO mutasi_stok (pengiriman_produk_id, tanggal, keterangan, gudang_id, produk_id, masuk, keluar, user_id) VALUES (?, ?, ?, ?, ?, 0, ?, ?)");
    $keteranganMutasi = "Pengiriman Produk / Surat Jalan - " . $kode;

    foreach ($items as $it) {
        $stmtMutasi->bind_param('issiidi', $pengiriman_id, $tanggal, $keteranganMutasi, $gudang_id, $it['produk_id'], $it['qty'], $user_id);
        $stmtMutasi->execute();
    }
    $stmtMutasi->close();

    // 4. POSTING KE BUKU BESAR: HPP (Debit) dan Persediaan Barang Jadi (Kredit)
    if ($total_hpp > 0) {
        $keteranganBB = "Harga Pokok Penjualan - {$kode}.";
        $stmtBB = $conn->prepare("INSERT INTO buku_besar (tanggal, pengiriman_produk_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, ?, ?, ?, ?, ?)");

        $postingAkun = [
            ['akun_id' => $akunHppId,        'debit' => $total_hpp, 'kredit' => 0.0],
            ['akun_id' => $akunPersediaanId, 'debit' => 0.0,        'kredit' => $total_hpp],
        ];

        foreach ($postingAkun as $row) {
            $stmtBB->bind_param('siiisdd', $tanggal, $pengiriman_id, $row['akun_id'], $user_id, $keteranganBB, $row['debit'], $row['kredit']);
            $stmtBB->execute();
        }
        $stmtBB->close();
    }

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Pengiriman produk dan jurnal HPP berhasil disimpan.'];
    header('Location: pengiriman_produk_index.php');
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()];
    header('Location: pengiriman_produk_tambah.php');
    exit;
}