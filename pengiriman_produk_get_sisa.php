<?php
include 'config.php';
require_once 'pengiriman_produk_core.php';

header('Content-Type: application/json; charset=utf-8');

$penjualanId = (int)($_GET['id'] ?? 0);
$gudangId    = (int)($_GET['gudang_id'] ?? 0);

if ($penjualanId <= 0) {
    echo json_encode(['success' => false, 'data' => []]);
    exit;
}

$sisaPerProduk = ambilSisaQtyPenjualan($conn, $penjualanId);
$ids = array_keys(array_filter($sisaPerProduk, fn($s) => $s['sisa'] > 0.0001));

if (empty($ids)) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$in    = implode(',', array_map('intval', $ids));
$hasil = $conn->query("SELECT id, kode, nama, satuan FROM produk WHERE id IN ($in) ORDER BY nama ASC");

$data = [];
while ($p = $hasil->fetch_assoc()) {
    $pid = (int)$p['id'];
    $s   = $sisaPerProduk[$pid];
    $data[] = [
        'produk_id'   => $pid,
        'kode'        => $p['kode'],
        'nama_produk' => $p['nama'],
        'satuan'      => $p['satuan'],
        'pesan'       => $s['pesan'],
        'kirim'       => $s['kirim'],
        'sisa'        => $s['sisa'],
        'stok'        => $gudangId > 0 ? ambilStokGudang($conn, $pid, $gudangId) : null,
    ];
}

echo json_encode(['success' => true, 'data' => $data]);