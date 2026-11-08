<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: penjualan_index.php');
    exit;
}

$tanggal      = trim($_POST['tanggal'] ?? '');
$kode         = trim($_POST['kode'] ?? '');
$pelanggan_id = (int)($_POST['pelanggan_id'] ?? 0);
$user_id      = $_SESSION['sipk-user_id'];

$pajak        = (float)($_POST['pajak'] ?? 0);
$ongkir       = (float)($_POST['ongkir'] ?? 0);
$biaya        = (float)($_POST['biaya'] ?? 0);
$diskon       = (float)($_POST['diskon'] ?? 0);

$pid         = $_POST['produk_id'] ?? [];
$qtys        = $_POST['qty'] ?? [];
$hargas      = $_POST['harga'] ?? [];
$hargaPokoks = $_POST['harga_pokok'] ?? [];

$items = [];
$subtotal = 0.0;
$subtotal_pokok = 0.0;

foreach ($pid as $i => $p) {
    $produk_id = (int)$p;
    if ($produk_id <= 0) continue;

    $q  = (float)($qtys[$i] ?? 0);
    $h  = (float)($hargas[$i] ?? 0);
    $hp = (float)($hargaPokoks[$i] ?? 0);
    
    $total = round($q * $h, 2);
    $totalPokok = round($q * $hp, 2);

    $items[] = [
        'produk_id'   => $produk_id, 
        'qty'         => $q, 
        'harga'       => $h, 
        'harga_pokok' => $hp, 
        'total'       => $total
    ];
    
    $subtotal += $total;
    $subtotal_pokok += $totalPokok;
}

if (empty($items)) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Minimal satu produk penjualan harus diisi.'];
    header('Location: penjualan_tambah.php');
    exit;
}

$grand_total = round($subtotal + $pajak + $ongkir + $biaya - $diskon, 2);

// Keterangan buku besar
$keteranganBukuBesar = "Penjualan Barang - {$kode}.";

// Mapping ID Akun
// Catatan: HPP (akun 20) dan Persediaan (akun 14) TIDAK diposting di sini lagi,
// melainkan saat barang dikirim (pengiriman_produk_simpan.php).
$akunPiutangId     = 16; // Piutang Pelanggan (Debit)
$akunPendapatanId  = 17; // Pendapatan Penjualan Barang Jadi (Kredit)
$akunDiskonId      = 18; // Diskon Penjualan (Debit)
$akunPajakKeluaran = 19; // PPN Keluaran / Hutang Pajak Penjualan (Kredit)

$conn->begin_transaction();
try {
    // 1. SIMPAN KE TABEL PENJUALAN & DETAILNYA
    // subtotal_pokok & harga_pokok tetap disimpan: dipakai sebagai dasar HPP saat pengiriman
    $stmt = $conn->prepare("INSERT INTO penjualan (tanggal, kode, pelanggan_id, user_id, subtotal, subtotal_pokok, pajak, ongkir, biaya, diskon, grand_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssiiddddddd', $tanggal, $kode, $pelanggan_id, $user_id, $subtotal, $subtotal_pokok, $pajak, $ongkir, $biaya, $diskon, $grand_total);
    $stmt->execute();
    $penjualan_id = $conn->insert_id;
    $stmt->close();

    $n = count($items);
    $sqlDetail = "INSERT INTO penjualan_detail (penjualan_id, produk_id, qty, harga, harga_pokok, total) VALUES " . implode(',', array_fill(0, $n, '(?,?,?,?,?,?)'));
    $params = [];
    foreach ($items as $it) {
        array_push($params, $penjualan_id, $it['produk_id'], $it['qty'], $it['harga'], $it['harga_pokok'], $it['total']);
    }
    $stmtDetail = $conn->prepare($sqlDetail);
    $stmtDetail->bind_param(str_repeat('iidddd', $n), ...$params);
    $stmtDetail->execute();
    $stmtDetail->close();

    // 2. POSTING OTOMATIS KE BUKU BESAR (sisi penjualan: piutang & pendapatan saja)
    $stmtBB = $conn->prepare("INSERT INTO buku_besar (tanggal, penjualan_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $postingAkun = [];

    // A. Piutang Pelanggan (Debit sebesar grand total)
    if ($grand_total > 0) {
        $postingAkun[] = ['akun_id' => $akunPiutangId, 'debit' => $grand_total, 'kredit' => 0.0];
    }
    // B. Diskon Penjualan (Debit)
    if ($diskon > 0) {
        $postingAkun[] = ['akun_id' => $akunDiskonId, 'debit' => $diskon, 'kredit' => 0.0];
    }
    // C. Pendapatan Penjualan (Kredit sebesar subtotal + ongkir + biaya)
    $totalPendapatan = $subtotal + $ongkir + $biaya;
    if ($totalPendapatan > 0) {
        $postingAkun[] = ['akun_id' => $akunPendapatanId, 'debit' => 0.0, 'kredit' => $totalPendapatan];
    }
    // D. PPN Keluaran / Pajak Penjualan (Kredit)
    if ($pajak > 0) {
        $postingAkun[] = ['akun_id' => $akunPajakKeluaran, 'debit' => 0.0, 'kredit' => $pajak];
    }

    foreach ($postingAkun as $row) {
        $stmtBB->bind_param('siiisdd', $tanggal, $penjualan_id, $row['akun_id'], $user_id, $keteranganBukuBesar, $row['debit'], $row['kredit']);
        $stmtBB->execute();
    }
    $stmtBB->close();

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data penjualan dan jurnal buku besar berhasil disimpan.'];
    header('Location: penjualan_index.php');
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()];
    header('Location: penjualan_tambah.php');
    exit;
}