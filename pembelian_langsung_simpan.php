<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pembelian_langsung_index.php');
    exit;
}

$tanggal    = trim($_POST['tanggal'] ?? '');
$kode       = trim($_POST['kode'] ?? '');
$pemasok_id = (int)($_POST['pemasok_id'] ?? 0);
$user_id    = 1; // Sesuaikan dengan session user login, misal: $_SESSION['user_id'] ?? 1

$pajak      = (float)($_POST['pajak'] ?? 0);
$ongkir     = (float)($_POST['ongkir'] ?? 0);
$biaya      = (float)($_POST['biaya'] ?? 0);
$diskon     = (float)($_POST['diskon'] ?? 0);

$pid    = $_POST['produk_id'] ?? [];
$qtys   = $_POST['qty'] ?? [];
$hargas = $_POST['harga'] ?? [];

$items = [];
$subtotal = 0.0;

foreach ($pid as $i => $p) {
    $produk_id = (int)$p;
    if ($produk_id <= 0) continue;

    $q = (float)($qtys[$i] ?? 0);
    $h = (float)($hargas[$i] ?? 0);
    $total = round($q * $h, 2);

    $items[] = ['produk_id' => $produk_id, 'qty' => $q, 'harga' => $h, 'total' => $total];
    $subtotal += $total;
}

if (empty($items)) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Minimal satu produk harus diisi.'];
    header('Location: pembelian_langsung_tambah.php');
    exit;
}

$grand_total = round($subtotal + $pajak + $ongkir + $biaya - $diskon, 2);

// Keterangan transaksi & format keterangan buku besar
$keteranganDetail = "Pembelian Langsung";
$keteranganBukuBesar = "{$keteranganDetail} - {$kode}.";

// Mapping ID Akun (sesuaikan dengan database Anda)
$akunPersediaanId = 3;   // Persediaan Bahan Baku Belum Diterima (Debit)
$akunHutangId     = 5;   // Hutang Pemasok (Kredit)
$akunPajakId      = 9;   // Pajak Masukan (Debit)
$akunOngkirId     = 10;  // Beban Angkut / Ongkir (Debit)
$akunBiayaId      = 11;  // Beban Operasional Lainnya (Debit)
$akunDiskonId     = 12;  // Diskon Pembelian (Kredit)

$conn->begin_transaction();
try {
    // =========================================================
    // 1. SIMPAN KE TABEL PEMBELIAN LANGSUNG & DETAILNYA
    // =========================================================
    $stmt = $conn->prepare("INSERT INTO pembelian_langsung (tanggal, kode, pemasok_id, user_id, subtotal, pajak, ongkir, biaya, diskon, grand_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssiiiddddd', $tanggal, $kode, $pemasok_id, $user_id, $subtotal, $pajak, $ongkir, $biaya, $diskon, $grand_total);
    $stmt->execute();
    $pembelian_id = $conn->insert_id;
    $stmt->close();

    $n = count($items);
    $sqlDetail = "INSERT INTO pembelian_langsung_detail (pembelian_langsung_id, produk_id, qty, harga, total) VALUES " . implode(',', array_fill(0, $n, '(?,?,?,?,?)'));
    $params = [];
    foreach ($items as $it) {
        array_push($params, $pembelian_id, $it['produk_id'], $it['qty'], $it['harga'], $it['total']);
    }
    $stmtDetail = $conn->prepare($sqlDetail);
    $stmtDetail->bind_param(str_repeat('iiddd', $n), ...$params);
    $stmtDetail->execute();
    $stmtDetail->close();

    // =========================================================
    // 2. POSTING OTOMATIS KE BUKU BESAR (menggunakan pembelian_langsung_id)
    // =========================================================
    $stmtBB = $conn->prepare("INSERT INTO buku_besar (tanggal, pembelian_langsung_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $postingAkun = [];

    // A. Persediaan (Debit sebesar subtotal)
    if ($subtotal > 0) {
        $postingAkun[] = ['akun_id' => $akunPersediaanId, 'debit' => $subtotal, 'kredit' => 0.0];
    }
    // B. Pajak (Debit)
    if ($pajak > 0) {
        $postingAkun[] = ['akun_id' => $akunPajakId, 'debit' => $pajak, 'kredit' => 0.0];
    }
    // C. Ongkir (Debit)
    if ($ongkir > 0) {
        $postingAkun[] = ['akun_id' => $akunOngkirId, 'debit' => $ongkir, 'kredit' => 0.0];
    }
    // D. Biaya Lain (Debit)
    if ($biaya > 0) {
        $postingAkun[] = ['akun_id' => $akunBiayaId, 'debit' => $biaya, 'kredit' => 0.0];
    }
    // E. Diskon (Kredit)
    if ($diskon > 0) {
        $postingAkun[] = ['akun_id' => $akunDiskonId, 'debit' => 0.0, 'kredit' => $diskon];
    }
    // F. Hutang Pemasok (Kredit sebesar grand total)
    if ($grand_total > 0) {
        $postingAkun[] = ['akun_id' => $akunHutangId, 'debit' => 0.0, 'kredit' => $grand_total];
    }

    // Eksekusi insert ke buku_besar
    foreach ($postingAkun as $row) {
        $stmtBB->bind_param('siiisdd', $tanggal, $pembelian_id, $row['akun_id'], $user_id, $keteranganBukuBesar, $row['debit'], $row['kredit']);
        $stmtBB->execute();
    }
    $stmtBB->close();

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data pembelian langsung berhasil disimpan.'];
    header('Location: pembelian_langsung_index.php');
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()];
    header('Location: pembelian_langsung_tambah.php');
    exit;
}