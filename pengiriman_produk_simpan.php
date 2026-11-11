<?php
include 'config.php';
require_once 'pengiriman_produk_core.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pengiriman_produk_index.php');
    exit;
}

$tanggal      = trim($_POST['tanggal'] ?? '');
$kode         = trim($_POST['kode'] ?? '');
$penjualan_id = (int)($_POST['penjualan_id'] ?? 0);
$gudang_id    = (int)($_POST['gudang_id'] ?? 0);
$user_id      = $_SESSION['sipk-user_id'];
$pid          = $_POST['produk_id'] ?? [];
$qtys         = $_POST['qty'] ?? [];

if (empty($tanggal) || empty($kode) || $penjualan_id <= 0 || $gudang_id <= 0 || !is_array($pid)) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Data pengiriman tidak lengkap.'];
    header('Location: pengiriman_produk_tambah.php');
    exit;
}

// Gabungkan produk yang sama, abaikan baris kosong / qty <= 0
$qtyPerProduk = [];
foreach ($pid as $i => $p) {
    $produk_id = (int)$p;
    $qty       = (float)($qtys[$i] ?? 0);
    if ($produk_id <= 0 || $qty <= 0) continue;
    $qtyPerProduk[$produk_id] = ($qtyPerProduk[$produk_id] ?? 0.0) + $qty;
}
ksort($qtyPerProduk); // urutan kunci konsisten, mencegah deadlock

if (empty($qtyPerProduk)) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Item pengiriman tidak ditemukan.'];
    header('Location: pengiriman_produk_tambah.php');
    exit;
}

$akunHppId        = 20; // 501.02 Harga Pokok Penjualan (Debit)
$akunPersediaanId = 14; // 103.04 Persediaan Barang Jadi (Kredit)

$conn->begin_transaction();
try {
    // 1. Kunci penjualan agar dua pengiriman bersamaan tidak sama-sama lolos
    $stmtLock = $conn->prepare("SELECT id FROM penjualan WHERE id = ? FOR UPDATE");
    $stmtLock->bind_param('i', $penjualan_id);
    $stmtLock->execute();
    $ada = $stmtLock->get_result()->fetch_assoc();
    $stmtLock->close();
    if (!$ada) {
        throw new Exception('Faktur penjualan tidak ditemukan.');
    }

    // 2. Qty kirim tidak boleh melebihi sisa
    $sisaPerProduk = ambilSisaQtyPenjualan($conn, $penjualan_id);

    // 3. Validasi per produk: sisa, stok gudang, harga pokok
    $stmtProduk = $conn->prepare("SELECT nama, harga_pokok FROM produk WHERE id = ? FOR UPDATE");
    $items       = [];
    $total_do    = 0.0;
    $total_hpp   = 0.0;

    foreach ($qtyPerProduk as $produk_id => $qty) {
        if (!isset($sisaPerProduk[$produk_id])) {
            throw new Exception('Ada produk yang tidak terdapat pada penjualan ini (ID produk: ' . $produk_id . ').');
        }

        $stmtProduk->bind_param('i', $produk_id);
        $stmtProduk->execute();
        $produk = $stmtProduk->get_result()->fetch_assoc();
        if (!$produk) {
            throw new Exception('Produk tidak ditemukan (ID produk: ' . $produk_id . ').');
        }

        $sisa = $sisaPerProduk[$produk_id]['sisa'];
        if ($qty > $sisa + 0.0001) {
            throw new Exception('Qty kirim "' . $produk['nama'] . '" melebihi sisa pesanan (sisa ' . $sisa . ', diinput ' . $qty . ').');
        }

        $stok = ambilStokGudang($conn, $produk_id, $gudang_id);
        if ($qty > $stok + 0.0001) {
            throw new Exception('Stok "' . $produk['nama'] . '" di gudang ini tidak cukup (tersedia ' . $stok . ').');
        }

        $hargaPokok = (float)$produk['harga_pokok'];
        if ($hargaPokok <= 0) {
            throw new Exception('Harga pokok "' . $produk['nama'] . '" belum terisi di master produk.');
        }

        $harga = $sisaPerProduk[$produk_id]['harga']; // harga jual rata-rata dari faktur
        $tot   = round($qty * $harga, 2);

        $items[]    = ['produk_id' => $produk_id, 'qty' => $qty, 'harga' => $harga, 'total' => $tot];
        $total_do  += $tot;
        $total_hpp += round($qty * $hargaPokok, 2);
    }
    $stmtProduk->close();
    $total_do  = round($total_do, 2);
    $total_hpp = round($total_hpp, 2);

    // 4. Header pengiriman
    $stmt = $conn->prepare("INSERT INTO pengiriman_produk (tanggal, kode, penjualan_id, gudang_id, user_id, total) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssiiid', $tanggal, $kode, $penjualan_id, $gudang_id, $user_id, $total_do);
    $stmt->execute();
    $pengiriman_id = $conn->insert_id;
    $stmt->close();

    // 5. Detail + mutasi stok keluar
    $stmtDet    = $conn->prepare("INSERT INTO pengiriman_produk_detail (pengiriman_produk_id, produk_id, qty, harga, total) VALUES (?, ?, ?, ?, ?)");
    $stmtMutasi = $conn->prepare("INSERT INTO mutasi_stok (pengiriman_produk_id, tanggal, keterangan, gudang_id, produk_id, masuk, keluar, user_id) VALUES (?, ?, ?, ?, ?, 0, ?, ?)");
    $keteranganMutasi = "Pengiriman Produk / Surat Jalan - " . $kode;

    foreach ($items as $it) {
        $stmtDet->bind_param('iiddd', $pengiriman_id, $it['produk_id'], $it['qty'], $it['harga'], $it['total']);
        $stmtDet->execute();

        $stmtMutasi->bind_param('issiidi', $pengiriman_id, $tanggal, $keteranganMutasi, $gudang_id, $it['produk_id'], $it['qty'], $user_id);
        $stmtMutasi->execute();
    }
    $stmtDet->close();
    $stmtMutasi->close();

    // 6. Buku besar: Dr HPP / Cr Persediaan Barang Jadi
    $keteranganBB = "Harga Pokok Penjualan - {$kode}.";
    $stmtBB = $conn->prepare("INSERT INTO buku_besar (tanggal, pengiriman_produk_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $nol = 0.0;

    $stmtBB->bind_param('siiisdd', $tanggal, $pengiriman_id, $akunHppId, $user_id, $keteranganBB, $total_hpp, $nol);
    $stmtBB->execute();

    $stmtBB->bind_param('siiisdd', $tanggal, $pengiriman_id, $akunPersediaanId, $user_id, $keteranganBB, $nol, $total_hpp);
    $stmtBB->execute();
    $stmtBB->close();

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