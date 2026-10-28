<?php
include 'config.php';

$produk_id = (int)($_GET['produk_id'] ?? 0);
$gudang_id = (int)($_GET['gudang_id'] ?? 0);
$tipe      = $_GET['tipe'] ?? 'beli'; // 'beli' atau 'produksi'

if ($produk_id <= 0) {
    echo json_encode(['success' => false, 'harga' => 0]);
    exit;
}

$harga = 0;

// Jika untuk kebutuhan produksi (Serah Terima), utamakan harga_pokok internal master produk
if ($tipe === 'produksi') {
    $stmtProd = $conn->prepare("SELECT harga_pokok FROM produk WHERE id = ?");
    $stmtProd->bind_param('i', $produk_id);
    $stmtProd->execute();
    $resProd = $stmtProd->get_result()->fetch_assoc();
    $stmtProd->close();
    
    if ($resProd && (float)$resProd['harga_pokok'] > 0) {
        $harga = (float)$resProd['harga_pokok'];
    }
}

// Jika belum ketemu atau tipenya pembelian, cari dari riwayat penerimaan terakhir di gudang
if ($harga <= 0 && $gudang_id > 0) {
    $stmt = $conn->prepare("
        SELECT ppld.harga 
        FROM penerimaan_pembelian_langsung_detail ppld
        JOIN penerimaan_pembelian_langsung ppl ON ppl.id = ppld.penerimaan_pembelian_langsung_id
        WHERE ppld.produk_id = ? AND ppl.gudang_id = ?
        ORDER BY ppl.tanggal DESC, ppl.id DESC 
        LIMIT 1
    ");
    $stmt->bind_param('ii', $produk_id, $gudang_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($res) {
        $harga = (float)$res['harga'];
    }
}

// Fallback terakhir ke harga_beli master produk
if ($harga <= 0) {
    $stmtProd = $conn->prepare("SELECT harga_beli FROM produk WHERE id = ?");
    $stmtProd->bind_param('i', $produk_id);
    $stmtProd->execute();
    $resProd = $stmtProd->get_result()->fetch_assoc();
    $stmtProd->close();

    if ($resProd) {
        $harga = (float)$resProd['harga_beli'];
    }
}

echo json_encode(['success' => true, 'harga' => $harga]);