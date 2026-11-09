<?php
include 'config.php';
require_once 'penerimaan_pembelian_langsung_core.php';

header('Content-Type: application/json; charset=utf-8');

$pembelianId = (int)($_GET['id'] ?? 0);
if ($pembelianId <= 0) {
    echo json_encode(['success' => false, 'data' => []]);
    exit;
}

$sisaPerProduk = ambilSisaQtyPembelian($conn, $pembelianId);

$ids = array_keys(array_filter($sisaPerProduk, fn($s) => $s['sisa'] > 0.0001));
if (empty($ids)) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$in   = implode(',', array_map('intval', $ids));
$hasil = $conn->query("SELECT id, kode, nama, satuan FROM produk WHERE id IN ($in) ORDER BY nama ASC");

$data = [];
while ($p = $hasil->fetch_assoc()) {
    $s = $sisaPerProduk[(int)$p['id']];
    $data[] = [
        'produk_id'   => (int)$p['id'],
        'kode'        => $p['kode'],
        'nama_produk' => $p['nama'],
        'satuan'      => $p['satuan'],
        'pesan'       => $s['pesan'],
        'terima'      => $s['terima'],
        'sisa'        => $s['sisa'],
    ];
}

echo json_encode(['success' => true, 'data' => $data]);