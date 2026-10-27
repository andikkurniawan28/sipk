<?php
include 'config.php';

header('Content-Type: application/json; charset=utf-8');

$pembelian_id = (int)($_GET['id'] ?? 0);

if ($pembelian_id <= 0) {
    echo json_encode(['success' => false, 'total' => 0]);
    exit;
}

// Ambil total berdasarkan qty dari penerimaan dikalikan harga dari pembelian langsung detail
$stmt = $conn->prepare("
    SELECT SUM(ppld.qty * pld.harga) AS total_diterima
    FROM penerimaan_pembelian_langsung ppl
    JOIN penerimaan_pembelian_langsung_detail ppld ON ppld.penerimaan_pembelian_langsung_id = ppl.id
    JOIN pembelian_langsung_detail pld ON pld.pembelian_langsung_id = ppl.pembelian_langsung_id AND pld.produk_id = ppld.produk_id
    WHERE ppl.pembelian_langsung_id = ?
");
$stmt->bind_param('i', $pembelian_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalDiterima = (float)($res['total_diterima'] ?? 0);

echo json_encode(['success' => true, 'total' => $totalDiterima]);